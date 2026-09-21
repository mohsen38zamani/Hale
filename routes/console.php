<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Credits\Services\CreditService;
use App\Domains\AI\Services\CircuitBreaker;
use App\Domains\Generations\Models\Generation;
use App\Domains\Notifications\Notifications\GenerationStatusNotification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('media:cleanup-expired', function (): void {
    $count = 0;

    MediaAsset::query()->whereNotNull('expires_at')->where('expires_at', '<=', now())->chunkById(100, function ($assets) use (&$count): void {
        foreach ($assets as $asset) {
            Storage::disk($asset->disk)->delete(array_filter([$asset->path, $asset->thumbnail_path]));
            $asset->delete();
            $count++;
        }
    });

    $this->info("Deleted {$count} expired media assets.");
})->purpose('Delete expired generation media from storage and database');

Artisan::command('subscriptions:expire', function (SubscriptionService $subscriptions): void {
    $count = $subscriptions->expireAll();
    $this->info("Expired {$count} subscriptions.");
})->purpose('Expire subscriptions and restore users to their active plan');

Artisan::command('generations:recover-stale', function (CreditService $credits, CircuitBreaker $circuitBreaker): void {
    $count = 0;
    Generation::query()
        ->where('status', 'processing')
        ->where('processing_lease_expires_at', '<=', now())
        ->chunkById(100, function ($generations) use ($credits, $circuitBreaker, &$count): void {
            foreach ($generations as $generation) {
                $recovered = DB::transaction(function () use ($generation, $credits, $circuitBreaker): ?Generation {
                    $locked = Generation::query()->whereKey($generation->id)->lockForUpdate()->firstOrFail();
                    if ($locked->status !== 'processing' || $locked->processing_lease_expires_at === null || $locked->processing_lease_expires_at->isFuture()) {
                        return null;
                    }
                    $locked->update(['status' => 'failed', 'error_message' => 'پردازش بیش از زمان مجاز طول کشید.', 'processing_lease_expires_at' => null]);
                    $credits->refund($locked);
                    $circuitBreaker->release($locked->id);

                    return $locked->fresh();
                });
                if ($recovered !== null) {
                    $recovered->user->notify(new GenerationStatusNotification($recovered, 'failed'));
                    $count++;
                }
            }
        });
    $this->info("Recovered {$count} stale generations.");
})->purpose('Refund and fail generations stuck in processing');
