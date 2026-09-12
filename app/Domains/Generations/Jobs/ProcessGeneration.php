<?php

namespace App\Domains\Generations\Jobs;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Models\Generation;
use App\Domains\Notifications\Notifications\GenerationStatusNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessGeneration implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $uniqueFor = 300;

    public function __construct(public readonly int $generationId) {}

    public function uniqueId(): string
    {
        return (string) $this->generationId;
    }

    public function handle(AiGateway $gateway, CreditService $credits): void
    {
        $generation = Generation::query()->with('creativeProject.product.assets')->findOrFail($this->generationId);
        if (in_array($generation->status, ['processing', 'completed'], true)) {
            return;
        }

        $attempt = $generation->jobs()->create(['queue_job_id' => $this->job?->getJobId(), 'attempt' => $this->attempts(), 'status' => 'processing', 'started_at' => now()]);
        $startedAt = hrtime(true);
        $generation->update(['status' => 'processing', 'error_message' => null]);

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
            ));
            $result = $response['result'];
            $this->assertOutputContract($generation->type, $result->mime, $result->extension, $result->contents);
            $path = "generations/{$generation->user_id}/{$generation->id}.{$result->extension}";
            $disk = Storage::disk(config('ai.output_disk'));
            if (! $disk->put($path, $result->contents)) {
                throw new \RuntimeException('ذخیره خروجی generation ناموفق بود.');
            }
            $media = $generation->user->mediaAssets()->firstOrCreate(['path' => $path], ['disk' => config('ai.output_disk'), 'mime' => $result->mime, 'size' => strlen($result->contents), 'expires_at' => now()->addDays(config('ai.retention_days'))]);
            $elapsed = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            $generation->usageLogs()->create(['provider' => $response['provider'], 'model' => $result->model, 'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens, 'cost_usd' => $result->costUsd, 'metadata' => $result->metadata]);
            $generation->update(['status' => 'completed', 'provider' => $response['provider'], 'model' => $result->model, 'cost_usd' => $result->costUsd, 'processing_time_ms' => $elapsed, 'output_media_id' => $media->id]);
            $credits->settle($generation);
            $attempt->update(['status' => 'completed', 'finished_at' => now()]);
            try {
                $generation->user->notify(new GenerationStatusNotification($generation->fresh(), 'completed'));
            } catch (Throwable $notificationException) {
                report($notificationException);
            }
        } catch (Throwable $exception) {
            $attempt->update(['status' => 'failed', 'error_message' => $exception->getMessage(), 'finished_at' => now()]);
            $attemptNumber = max(1, $this->attempts());
            $generation->update(['status' => $attemptNumber >= $this->tries ? 'failed' : 'queued', 'error_message' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $generation = Generation::query()->find($this->generationId);
        if ($generation === null) {
            return;
        }

        $generation->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        app(CreditService::class)->refund($generation);
        $generation->user->notify(new GenerationStatusNotification($generation, 'failed'));
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
    }
}
