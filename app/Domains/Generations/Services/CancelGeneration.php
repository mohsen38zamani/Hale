<?php

namespace App\Domains\Generations\Services;

use App\Domains\AI\Services\CircuitBreaker;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Exceptions\GenerationNotCancellable;
use App\Domains\Generations\Models\Generation;
use App\Domains\Notifications\Notifications\GenerationStatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CancelGeneration
{
    public function execute(Generation $generation, string $reason): Generation
    {
        $cancelled = DB::transaction(function () use ($generation, $reason): Generation {
            $locked = Generation::query()->whereKey($generation->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, ['queued', 'processing'], true)) {
                throw new GenerationNotCancellable;
            }

            $locked->update([
                'status' => 'cancelled',
                'error_message' => $reason,
                'processing_lease_expires_at' => null,
            ]);

            // Refund is an atomic claim against settle, so a generation that
            // races us into completion is never double-credited. The job's own
            // guards (claim/settle check status) make it a no-op afterwards.
            app(CreditService::class)->refund($locked);
            app(CircuitBreaker::class)->release($locked->id);

            return $locked->fresh();
        });

        $this->deletePendingOutput($cancelled);

        try {
            $cancelled->user->notify(new GenerationStatusNotification($cancelled, 'cancelled'));
        } catch (Throwable $notificationException) {
            report($notificationException);
        }

        return $cancelled;
    }

    private function deletePendingOutput(Generation $generation): void
    {
        if ($generation->output_media_id !== null) {
            return;
        }

        $disk = Storage::disk(config('ai.output_disk'));
        foreach (['png', 'jpg', 'webp', 'mp4'] as $extension) {
            $disk->delete("generations/{$generation->user_id}/{$generation->id}.{$extension}");
        }
    }
}
