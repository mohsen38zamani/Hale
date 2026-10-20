<?php

namespace App\Domains\Generations\Jobs;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\AI\Services\CircuitBreaker;
use App\Domains\Creative\Services\ProductCompositor;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Contracts\EdgeProtectPassInterface;
use App\Domains\Generations\Models\Generation;
use App\Domains\Media\Services\ImageOptimizer;
use App\Domains\Media\Services\WatermarkService;
use App\Domains\Notifications\Notifications\GenerationStatusNotification;
use App\Domains\Search\Services\SearchIndexer;
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

    /**
     * Video is the only long-running output: Veo renders for minutes, and a
     * worker's default minute would cut it off mid-render. This is the
     * ceiling of every generation job, so the image path simply finishes
     * long before it - and it stays above the provider's own polling
     * budget, which gives up first and lets the chain fall back.
     */
    public int $timeout = 360;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $generationId)
    {
        $this->afterCommit = true;
    }

    public function uniqueId(): string
    {
        return (string) $this->generationId;
    }

    public function handle(AiGateway $gateway, CreditService $credits, ?WatermarkService $watermarks = null, ?CircuitBreaker $circuitBreaker = null, ?SearchIndexer $searchIndexer = null, ?ImageOptimizer $imageOptimizer = null, ?ProductCompositor $compositor = null, ?EdgeProtectPassInterface $edgeProtect = null): void
    {
        $watermarks ??= app(WatermarkService::class);
        $circuitBreaker ??= app(CircuitBreaker::class);
        $searchIndexer ??= app(SearchIndexer::class);
        $imageOptimizer ??= app(ImageOptimizer::class);
        $compositor ??= app(ProductCompositor::class);
        $edgeProtect ??= app(EdgeProtectPassInterface::class);

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

            // The catalog-white pipeline: when the project chose it, the
            // model receives the cutout already resting on pure white under
            // a grounding shadow - our own pixels, drawn by GD, no redraw
            // between the product and the page. A photo GD cannot read
            // still travels as it arrived: the frame is a promise about
            // pixels, not a gate on the generation.
            $assetDisk = $asset?->disk;
            $assetPath = $asset?->path;
            $compositedPath = null;
            if ($asset !== null && ($project->fidelity_mode?->isComposite() ?? false)) {
                try {
                    $staged = "composites/{$generation->user_id}/{$generation->id}.png";
                    $composited = $compositor->composite((string) Storage::disk($asset->disk)->get($asset->path));
                    if (Storage::disk(config('ai.output_disk'))->put($staged, $composited)) {
                        $compositedPath = $staged;
                        $assetDisk = (string) config('ai.output_disk');
                        $assetPath = $staged;
                    }
                } catch (Throwable $compositeException) {
                    report($compositeException);
                }
            }

            try {
                $response = $gateway->generate(new GenerationInput(
                    $generation->type,
                    $project->prompt,
                    $generation->metadata['aspect_ratio'],
                    $project->video_duration_seconds,
                    $assetDisk,
                    $assetPath,
                    $generation->id,
                    // How the photo above joins a video: as a reference, as the
                    // opening frame, or as both the opening and the closing
                    // one. The key is absent on generations queued before the
                    // choice existed, and 'reference' is what they got.
                    (string) ($generation->metadata['image_mode'] ?? 'reference'),
                ));
            } finally {
                // The staged composite served its one purpose the moment the
                // provider read it; a leftover on the output disk would be
                // litter nobody owns.
                if ($compositedPath !== null) {
                    try {
                        Storage::disk(config('ai.output_disk'))->delete($compositedPath);
                    } catch (Throwable) {
                        // Never let cleanup turn a settled call into a retry.
                    }
                }
            }
            $result = $response['result'];
            $providerFailures = $response['failures'] ?? [];
            // Quality tier: resize to the plan tier's dimension before the
            // watermark so the stamp is rendered at the final size.
            $contents = $result->contents;
            $edgeProtectStatus = null;
            $edgeProtectUsage = null;
            if ($generation->type === 'image'
                && ($project->fidelity_mode?->isInpainting() ?? false)
                && $asset !== null) {
                // Foreground-invariant inpainting's second act: the scene
                // exists, so the customer's product is landed again from
                // its own photo. The pass only ever adds quality on top of
                // a finished render - its failure keeps the render that
                // already exists, never a failed generation.
                $category = $project->category?->value ?? (is_string($project->brief['category'] ?? null) ? $project->brief['category'] : null);
                $edgeProtectAttempt = $edgeProtect->apply($result->contents, $result->mime, $asset, $category);
                if ($edgeProtectAttempt === null) {
                    $edgeProtectStatus = 'failed';
                } else {
                    $contents = $edgeProtectAttempt['contents'];
                    $edgeProtectStatus = 'applied';
                    $edgeProtectUsage = $edgeProtectAttempt;
                }
            }
            if ($generation->type === 'image') {
                $quality = (string) ($generation->metadata['quality'] ?? 'standard');
                $tier = $quality === 'premium' ? 'premium_max_dimension' : 'standard_max_dimension';
                $contents = $imageOptimizer->fitToMax($contents, (int) config("media.{$tier}", 1024), $quality === 'premium');
            }
            $contents = $watermarks->applyForPlan($contents, $result->mime, $generation->user->plan_key);
            $this->assertOutputContract($generation->type, $result->mime, $result->extension, $contents);
            $path = "generations/{$generation->user_id}/{$generation->id}.{$result->extension}";
            $disk = Storage::disk(config('ai.output_disk'));
            if (! $disk->put($path, $contents)) {
                throw new \RuntimeException('ذخیره خروجی generation ناموفق بود.');
            }
            $fileStored = true;
            $elapsed = (int) ((hrtime(true) - $startedAt) / 1_000_000);
            $completed = DB::transaction(function () use ($generation, $path, $result, $contents, $response, $providerFailures, $edgeProtectStatus, $edgeProtectUsage, $elapsed, $credits, $attempt, $circuitBreaker): bool {
                $generation = Generation::query()->whereKey($generation->id)->lockForUpdate()->firstOrFail();
                if ($generation->status !== 'processing' || $generation->processing_lease_expires_at?->isPast()) {
                    return false;
                }
                $media = $generation->user->mediaAssets()->firstOrCreate(['path' => $path], ['disk' => config('ai.output_disk'), 'mime' => $result->mime, 'size' => strlen($contents), 'expires_at' => now()->addDays(config('ai.retention_days'))]);
                $generation->usageLogs()->firstOrCreate([], ['provider' => $response['provider'], 'model' => $result->model, 'input_tokens' => $result->inputTokens, 'output_tokens' => $result->outputTokens, 'cost_usd' => $result->costUsd, 'metadata' => $result->metadata]);
                // The protect pass is a second, self-paid model call: it
                // belongs on the same ledger, clearly addressed, so margin
                // reporting can separate it from the render's own bill
                // without ever touching the user's credit charge.
                if ($edgeProtectUsage !== null) {
                    $generation->usageLogs()->firstOrCreate(
                        ['metadata->pass' => 'edge_protect'],
                        ['provider' => 'google_image_edit', 'model' => $edgeProtectUsage['model'], 'input_tokens' => $edgeProtectUsage['inputTokens'], 'output_tokens' => $edgeProtectUsage['outputTokens'], 'cost_usd' => $edgeProtectUsage['costUsd'], 'metadata' => ['pass' => 'edge_protect']]
                    );
                }
                $generation->update([
                    'status' => 'completed',
                    'provider' => $response['provider'],
                    'model' => $result->model,
                    'cost_usd' => $result->costUsd,
                    'processing_time_ms' => $elapsed,
                    'output_media_id' => $media->id,
                    'processing_lease_expires_at' => null,
                    // Which providers failed before this one succeeded, so a
                    // fallback run stays observable on the generation itself.
                    'metadata' => array_merge($generation->metadata === null ? [] : $generation->metadata, $providerFailures === [] ? [] : ['provider_failures' => $providerFailures], $edgeProtectStatus === null ? [] : ['edge_protect' => $edgeProtectStatus]),
                ]);
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
            // Keep the search index fresh; failures are logged inside the
            // indexer and must never fail a settled generation.
            $searchIndexer->indexGeneration($generation->fresh());
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
            // Never overwrite a cancellation that landed while the job was running.
            Generation::query()
                ->whereKey($this->generationId)
                ->where('status', '!=', 'cancelled')
                ->update([
                    'status' => $attemptNumber >= $this->tries ? 'failed' : 'queued',
                    'error_message' => $exception->getMessage(),
                    'processing_lease_expires_at' => null,
                ]);
            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        $generation = Generation::query()->find($this->generationId);
        if ($generation === null) {
            return;
        }

        if ($generation->status === 'cancelled') {
            // Already cancelled by an admin: refund, release and notification
            // were handled by CancelGeneration.
            Log::info("Generation was cancelled before job failure: #{$generation->id}", [
                'generation_id' => $generation->id,
                'error' => $exception->getMessage(),
            ]);

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
