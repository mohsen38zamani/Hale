<?php

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Providers\Google\GoogleImageEditProvider;
use App\Domains\AI\Providers\Google\GoogleImagenProvider;
use App\Domains\AI\Providers\Google\GoogleVeoProvider;
use App\Domains\AI\Services\CircuitBreaker;
use App\Domains\AI\Services\EnglishTranslator;
use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Models\Generation;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Notifications\Notifications\GenerationStatusNotification;
use App\Domains\Products\Models\Product;
use App\Domains\Search\Services\SearchIndexer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Minishlink\WebPush\VAPID;

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

Artisan::command('ai:check-budget-alert {--threshold=80 : Percentage threshold to trigger alert}', function (CircuitBreaker $circuitBreaker): void {
    $threshold = (float) $this->option('threshold');
    $alerted = $circuitBreaker->checkBudgetAlert($threshold);
    if ($alerted) {
        $this->warn("AI budget alert triggered and sent to administrators (threshold: {$threshold}%).");
    } else {
        $this->info('AI budget is within normal limits or alert was already sent for today.');
    }
})->purpose('Check daily AI budget usage and notify administrators if threshold is reached');

// Every capability Hale ships, asked of the real API exactly once. The
// providers under test are the production ones, so a green run makes the
// same claim production would: the endpoint exists, the fields are the
// ones the service reads, and the bytes come back where the code looks.
// Nothing here is verifiable from a box without a key, and everything
// below is what "verified" has to mean.
Artisan::command('ai:verify', function (): int {
    $apiKey = (string) config('ai.providers.google.api_key', '');
    if ($apiKey === '') {
        $this->error('GOOGLE_AI_API_KEY تنظیم نشده است؛ هیچ مسیری راستی‌آزمایی نمی‌شود.');

        return 1;
    }

    $this->warn('هر بررسی یک درخواستِ واقعی و پولیِ گوگل است؛ ویدئو چند دقیقه طول می‌کشد.');

    // A real, if tiny, PNG: both reference-image paths inline its bytes,
    // so they need a file on disk rather than a promise of one.
    $disk = 'local';
    $path = 'ai-verify/product.png';
    Storage::disk($disk)->put($path, (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));

    $translator = new EnglishTranslator;
    $translated = $translator->toEnglish('این یک آزمون کوتاه است.');

    $results = [
        'ترجمهٔ انگلیس' => $translated === null || $translated === 'این یک آزمون کوتاه است.'
            ? 'ناموفق — چیزی ترجمه نشد'
            : 'موفق — '.$translated,
    ];

    try {
        $result = (new GoogleImagenProvider)->generate(new GenerationInput('image', 'a plain white ceramic mug on a wooden table', '1:1'));
        $results['تصویر (Imagen)'] = "موفق — {$result->mime} · {$result->model}";
    } catch (Throwable $e) {
        $results['تصویر (Imagen)'] = 'ناموفق — '.$e->getMessage();
    }

    try {
        $result = (new GoogleImageEditProvider)->generate(new GenerationInput('image', 'the same mug, seen from the front, on a plain background', '1:1', null, $disk, $path));
        $results['تصویر با عکسِ محصول (نانو بنان)'] = "موفق — {$result->mime} · {$result->model}";
    } catch (Throwable $e) {
        $results['تصویر با عکسِ محصول (نانو بنان)'] = 'ناموفق — '.$e->getMessage();
    }

    try {
        $result = (new GoogleVeoProvider)->generate(new GenerationInput('video', 'the mug on a slowly turning table, soft morning light', '9:16', 5, $disk, $path));
        $results['ویدئو با عکسِ محصول (Veo)'] = "موفق — {$result->mime} · {$result->model}";
    } catch (Throwable $e) {
        $results['ویدئو با عکسِ محصول (Veo)'] = 'ناموفق — '.$e->getMessage();
    }

    // lastFrame is documented as image-to-video only, so this single call
    // asks for both frame fields at once and settles them together. When
    // it fails, a second call carrying image alone says which of the two
    // the service refused - and that second video is only ever paid for
    // when something is already wrong.
    $framePrompt = 'the mug holds still while morning light moves across it';
    try {
        $result = (new GoogleVeoProvider)->generate(new GenerationInput('video', $framePrompt, '9:16', 5, $disk, $path, null, 'first_last'));
        $results['ویدئو با قابِ اول و آخر (Veo)'] = "موفق — {$result->mime} · {$result->model}";
    } catch (Throwable $e) {
        $results['ویدئو با قابِ اول و آخر (Veo)'] = 'ناموفق — '.$e->getMessage();

        try {
            $result = (new GoogleVeoProvider)->generate(new GenerationInput('video', $framePrompt, '9:16', 5, $disk, $path, null, 'first'));
            $results['ویدئو با قابِ اول به‌تنهایی (Veo)'] = "موفق — {$result->mime} · {$result->model}";
        } catch (Throwable $frame) {
            $results['ویدئو با قابِ اول به‌تنهایی (Veo)'] = 'ناموفق — '.$frame->getMessage();
        }
    }

    Storage::disk($disk)->delete($path);

    $failed = 0;
    foreach ($results as $name => $outcome) {
        if (str_starts_with($outcome, 'موفق')) {
            $this->info("{$name}: {$outcome}");
        } else {
            $failed++;
            $this->error("{$name}: {$outcome}");
        }
    }

    $this->line(count($results)." مسیر بررسی شد؛ {$failed} ناموفق.");

    return $failed === 0 ? 0 : 1;
})->purpose('Run every AI capability against the real Google API once');

Artisan::command('search:reindex', function (SearchIndexer $indexer): void {
    $products = 0;
    Product::query()->chunkById(200, function ($chunk) use ($indexer, &$products): void {
        $indexer->pushProducts($chunk);
        $products += $chunk->count();
    });

    $generations = 0;
    Generation::query()->with('creativeProject.product')->chunkById(200, function ($chunk) use ($indexer, &$generations): void {
        $indexer->pushGenerations($chunk);
        $generations += $chunk->count();
    });

    $this->info("Indexed {$products} products and {$generations} generations.");
})->purpose('Push all products and generations into the Meilisearch indexes');

Artisan::command('webpush:generate-keys', function (): void {
    $keys = VAPID::createVapidKeys();

    $this->info('WEBPUSH_VAPID_PUBLIC_KEY='.$keys['publicKey']);
    $this->info('WEBPUSH_VAPID_PRIVATE_KEY='.$keys['privateKey']);
})->purpose('Generate a VAPID key pair for web push notifications');
