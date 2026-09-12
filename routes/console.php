<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Billing\Services\SubscriptionService;
use Illuminate\Support\Facades\Storage;

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
