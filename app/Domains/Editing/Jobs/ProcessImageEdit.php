<?php

namespace App\Domains\Editing\Jobs;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\AI\Services\CircuitBreaker;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Editing\Models\ImageEdit;
use App\Domains\Editing\Services\EditPromptBuilder;
use App\Domains\Editing\Services\EditSourceResolver;
use App\Domains\Media\Services\ImageOptimizer;
use App\Domains\Media\Services\WatermarkService;
use App\Domains\Notifications\Notifications\ImageEditStatusNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Runs one utility-tool edit: resolve source -> prompt -> provider ->
 * clamp/watermark -> store output -> settle. Credits were charged upfront
 * at creation, so terminal failure refunds them exactly once (idempotent
 * key), mirroring ProcessGeneration's reserve/settle lifecycle.
 *
 * Budget accounting cannot use the generation-keyed reservation table
 * (its generation FK is NOT NULL), so spend is recorded directly through
 * CircuitBreaker::recordSpend() after a successful settle.
 */
class ProcessImageEdit implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public int $uniqueFor = 300;

    public function __construct(public readonly int $imageEditId)
    {
        $this->afterCommit = true;
    }

    public function uniqueId(): string
    {
        return (string) $this->imageEditId;
    }

    public function handle(AiGateway $gateway, CreditService $credits, ?EditSourceResolver $resolver = null, ?EditPromptBuilder $prompts = null, ?WatermarkService $watermarks = null, ?CircuitBreaker $circuitBreaker = null, ?ImageOptimizer $imageOptimizer = null): void
    {
        $resolver ??= app(EditSourceResolver::class);
        $prompts ??= app(EditPromptBuilder::class);
        $watermarks ??= app(WatermarkService::class);
        $circuitBreaker ??= app(CircuitBreaker::class);
        $imageOptimizer ??= app(ImageOptimizer::class);

        $claimed = DB::transaction(function (): ?ImageEdit {
            $edit = ImageEdit::query()->whereKey($this->imageEditId)->lockForUpdate()->firstOrFail();
            if ($edit->status !== 'queued') {
                return null;
            }
            $edit->update([
                'status' => 'processing',
                'error_message' => null,
                'processing_lease_expires_at' => now()->addSeconds(config('ai.processing_lease_seconds')),
            ]);

            return $edit->fresh();
        });
        if ($claimed === null) {
            return;
        }
        $edit = $claimed;
        $startedAt = hrtime(true);
        $fileStored = false;
        $disk = null;
        $path = null;

        try {
            $source = $resolver->resolve($edit->source_type, (int) $edit->source_id, $edit->user);
            $options = (array) $edit->options;
            $response = $gateway->generate(new GenerationInput(
                type: 'image_edit',
                prompt: $prompts->build($edit->operation, $options),
                aspectRatio: (string) ($options['aspect_ratio'] ?? '1:1'),
                assetDisk: $source['disk'],
                assetPath: $source['path'],
                // Edits charge credits upfront: no generation-keyed budget
                // reservation exists, spend is recorded after settle.
                generationId: null,
            ));
            $result = $response['result'];
            $providerFailures = $response['failures'] ?? [];

            // Clamp: upscale steps up to its paid resolution, the other
            // tools are capped at the premium tier dimension.
            $targetDimension = match ($edit->operation) {
                'upscale' => match ($options['target'] ?? 'hd') {
                    '2k' => 2048,
                    '4k' => 4096,
                    default => 1024,
                },
                default => (int) config('media.premium_max_dimension', 2048),
            };
            $contents = $imageOptimizer->fitToMax($result->contents, $targetDimension, $edit->operation === 'upscale');
            $contents = $watermarks->applyForPlan($contents, $result->mime, $edit->user->plan_key);
            $this->assertOutputContract($result->mime, $contents);

            $path = "edits/{$edit->user_id}/{$edit->id}.{$result->extension}";
            $disk = Storage::disk(config('ai.output_disk'));
            if (! $disk->put($path, $contents)) {
                throw new RuntimeException('ذخیره خروجی ویرایش ناموفق بود.');
            }
            $fileStored = true;
            $elapsed = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            $completed = DB::transaction(function () use ($edit, $path, $result, $contents, $response, $providerFailures, $elapsed, $circuitBreaker): bool {
                $edit = ImageEdit::query()->whereKey($edit->id)->lockForUpdate()->firstOrFail();
                if ($edit->status !== 'processing' || $edit->processing_lease_expires_at?->isPast()) {
                    return false;
                }
                $media = $edit->user->mediaAssets()->firstOrCreate(
                    ['path' => $path],
                    ['disk' => config('ai.output_disk'), 'mime' => $result->mime, 'size' => strlen($contents), 'expires_at' => now()->addDays(config('ai.retention_days'))]
                );
                $metadata = (array) $edit->metadata;
                if ($providerFailures !== []) {
                    // Which providers failed before this one succeeded, so a
                    // fallback run stays observable on the edit itself.
                    $metadata['provider_failures'] = $providerFailures;
                }
                if ($result->metadata !== []) {
                    $metadata['provider'] = $result->metadata;
                }
                $edit->update([
                    'status' => 'completed',
                    'provider' => $response['provider'],
                    'model' => $result->model,
                    'cost_usd' => $result->costUsd,
                    'processing_time_ms' => $elapsed,
                    'output_media_id' => $media->id,
                    'processing_lease_expires_at' => null,
                    'metadata' => $metadata,
                ]);
                $circuitBreaker?->recordSpend($result->costUsd);

                return true;
            });
            if (! $completed) {
                // Lease expired or row cancelled meanwhile: drop the output.
                $disk->delete($path);

                return;
            }

            try {
                $edit->user->notify(new ImageEditStatusNotification($edit->fresh(), 'completed'));
            } catch (Throwable $notificationException) {
                report($notificationException);
            }
        } catch (Throwable $exception) {
            if ($fileStored && $disk !== null && $path !== null) {
                try {
                    $disk->delete($path);
                } catch (Throwable) {
                    // Ignore storage cleanup error on failure
                }
            }
            $attemptNumber = max(1, $this->attempts());
            ImageEdit::query()->whereKey($this->imageEditId)->update([
                'status' => $attemptNumber >= $this->tries ? 'failed' : 'queued',
                'error_message' => $exception->getMessage(),
                'processing_lease_expires_at' => null,
            ]);

            throw $exception;
        }
    }

    /**
     * Terminal failure: return the upfront charge exactly once and notify
     * the user. Both the refund key and the failed_handled metadata flag
     * are idempotent, so a replayed/concurrent failed() can neither refund
     * twice nor notify twice.
     */
    public function failed(Throwable $exception): void
    {
        $edit = ImageEdit::query()->find($this->imageEditId);
        if ($edit === null || $edit->status === 'completed') {
            return;
        }

        $metadata = (array) $edit->metadata;
        if (! empty($metadata['failed_handled'])) {
            return;
        }

        try {
            $edit->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'processing_lease_expires_at' => null,
                'metadata' => array_merge($metadata, ['failed_handled' => true]),
            ]);
            if ($edit->credits_spent > 0) {
                app(CreditService::class)->refundTask(
                    $edit->user,
                    (int) $edit->credits_spent,
                    "edit:{$edit->id}:refund",
                    ['edit_id' => $edit->id, 'operation' => $edit->operation],
                );
            }
            $edit->user->notify(new ImageEditStatusNotification($edit->fresh(), 'failed'));
        } catch (Throwable $failureException) {
            report($failureException);
            Log::error('image_edit.failed_handler_error', [
                'edit_id' => $this->imageEditId,
                'error' => $failureException->getMessage(),
            ]);
        }
    }

    private function assertOutputContract(string $mime, string $contents): void
    {
        if ($contents === '' || strlen($contents) > (int) config('ai.max_output_bytes')) {
            throw new RuntimeException('اندازه خروجی ویرایش معتبر نیست.');
        }

        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new RuntimeException('فرمت خروجی ویرایش تصویر پشتیبانی نمی‌شود.');
        }
    }
}
