<?php

namespace App\Domains\Generations\Jobs;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\AI\Services\CircuitBreaker;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Models\Generation;
use App\Domains\Media\Services\WatermarkService;
use App\Domains\Notifications\Notifications\GenerationStatusNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessGeneration implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $uniqueFor = 300;

    public function __construct(public readonly int $generationId)
    {
        $this->afterCommit = true;
    }

    public function uniqueId(): string
    {
        return (string) $this->generationId;
    }

    public function handle(AiGateway $gateway, CreditService $credits, ?WatermarkService $watermarks = null, ?CircuitBreaker $circuitBreaker = null): void
    {
        $watermarks ??= app(WatermarkService::class);
        $claimed = DB::transaction(function (): ?array {
            $generation = Generation::query()->whereKey($this->generationId)->lockForUpdate()->firstOrFail();
            if ($generation->status !== 'queued') {
                return null;
            }
            $generation->update([
                'status' => 'processing',
                'error_message' => null,
                'processing_lease_expires_at' => now()->addSeconds(config('ai.processing_lease_seconds')),
            ]);
            $attempt = $generation->jobs()->create(['queue_job_id' => $this->job?->getJobId(), 'attempt' => $this->attempts(), 'status' => 'processing', 'started_at' => now()]);

            return [$generation->fresh(), $attempt];
        });
        if ($claimed === null) {
            return;
        }
        [$generation, $attempt] = $claimed;
        $generation->load('creativeProject.product.assets');
        $startedAt = hrtime(true);

        try {
            $project = $generation->creativeProject;
            $asset = $project->product?->assets->first(fn ($asset) => (bool) $asset->pivot->is_primary) ?? $project->product?->assets->first();
            $response = $gateway->generate(new GenerationInput(
                $generation->type,
                $project->prompt,
                $generation->metadata['aspect_ratio'],
                $project->video_duration_seconds,
                $asset?->disk,
                $asset?->path,
                $generation->id,
            ));
            $result = $response['result'];
            $contents = $watermarks->applyForPlan($result->contents, $result->mime, $generation->user->plan_key);
            $this->assertOutputContract($generation->type, $result->mime, $result->extension, $contents);
            $path = "generations/{$generation->user_id}/{$generation->id}.{$result->extension}";
            $disk = Storage::disk(config('ai.output_disk'));
            if (! $disk->put($path, $contents)) {
                throw new \RuntimeException('ذخیره خروجی generation ناموفق بود.');
            }
            $fileStored = true;
            $elapsed = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $completed = DB::transaction(function () use ($generation, $path, $result, $contents, $response, $elapsed, $credits, $attempt, $circuitBreaker): bool {
                $generation = Generation::query()->whereKey($generation->id)->lockForUpdate()->firstOrFail();
                if ($generation->status !== 'processing' || $generation->processing_lease_expires_at?->isPast()) {
                    return false;
                }
                $media = $generation->user->mediaAssets()->firstOrCreate(['path' => $path], ['disk' => config('ai.output_disk'), 'mime' => $result->mime, 'size' => strlen($contents), 'expires_at' => now()->addDays(config('ai.retention_days'))]);
                $generation->usageLogs()->firstOrCreate([], ['provider' => $response['provider'], 'model' => $result->model, 'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens, 'cost_usd' => $result->costUsd, 'metadata' => $result->metadata]);
                $generation->update(['status' => 'completed', 'provider' => $response['provider'], 'model' => $result->model, 'cost_usd' => $result->costUsd, 'processing_time_ms' => $elapsed, 'output_media_id' => $media->id, 'processing_lease_expires_at' => null]);
                $credits->settle($generation);
                $circuitBreaker?->settle($generation->id, $result->costUsd);
                $attempt->update(['status' => 'completed', 'finished_at' => now()]);

                return true;
            });
            if (! $completed) {
                $disk->delete($path);
                $circuitBreaker?->release($generation->id);

                return;
            }
            try {
                $generation->user->notify(new GenerationStatusNotification($generation->fresh(), 'completed'));
            } catch (Throwable $notificationException) {
                report($notificationException);
            }
        } catch (Throwable $exception) {
            if (! empty($fileStored) && isset($disk, $path)) {
                try {
                    $disk->delete($path);
                } catch (Throwable) {
                    // Ignore storage cleanup error on failure
                }
            }
            $circuitBreaker?->release($generation->id);
            $attempt->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'finished_at' => now()]);
            $attemptNumber = max(1, $this->attempts());
            $generation->update(['status' => $attemptNumber >= $this->tries ? 'failed' : 'queued', 'error_message' => $exception->getMessage(), 'processing_lease_expires_at' => null]);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $generation = Generation::query()->find($this->generationId);
        if ($generation === null) {
            return;
        }

        $generation->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'processing_lease_expires_at' => null]);
        app(CreditService::class)->refund($generation);
        app(CircuitBreaker::class)->release($generation->id);
        if ($generation->output_media_id === null) {
            $disk = Storage::disk(config('ai.output_disk'));
            foreach (['png', 'jpg', 'webp', 'mp4'] as $extension) {
                $disk->delete("generations/{$generation->user_id}/{$generation->id}.{$extension}");
            }
        }
        try {
            $generation->user->notify(new GenerationStatusNotification($generation, 'failed'));
        } catch (Throwable $notificationException) {
            report($notificationException);
        }

        Log::error("Generation pipeline failed permanently: #{$generation->id}", [
            'generation_id' => $generation->id,
            'user_id' => $generation->user_id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }

    private function assertOutputContract(string $type, string $mime, string $extension, string $contents): void
    {
        $expected = $type === 'video' ? ['video/mp4', 'mp4'] : [['image/png', 'png'], ['image/jpeg', 'jpg'], ['image/webp', 'webp']];

        if ($contents === '' || strlen($contents) > config('ai.max_output_bytes')) {
            throw new \RuntimeException('اندازه خروجی generation معتبر نیست.');
        }

        if ($type === 'video' && $mime !== $expected[0]) {
            throw new \RuntimeException('Provider خروجی ویدئو با MIME video/mp4 تولید نکرد.');
        }

        if ($type === 'video' && $extension !== $expected[1]) {
            throw new \RuntimeException('Provider خروجی ویدئو با پسوند mp4 تولید نکرد.');
        }

        if ($type === 'video' && (strlen($contents) < 12 || substr($contents, 4, 4) !== 'ftyp')) {
            throw new \RuntimeException('محتوای خروجی ویدئو MP4 معتبر نیست.');
        }

        if ($type === 'image' && ! in_array([$mime, $extension], $expected, true)) {
            throw new \RuntimeException('Provider خروجی تصویر با MIME و پسوند معتبر تولید نکرد.');
        }

        if ($type === 'image' && @getimagesizefromstring($contents) === false) {
            throw new \RuntimeException('محتوای خروجی تصویر معتبر نیست.');
        }
    }
}
