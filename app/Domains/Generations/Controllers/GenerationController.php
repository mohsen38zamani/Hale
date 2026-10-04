<?php

namespace App\Domains\Generations\Controllers;

use App\Domains\AI\Services\PromptModerator;
use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Services\CreativeEngine;
use App\Domains\Credits\Exceptions\InsufficientCredits;
use App\Domains\Credits\Exceptions\PlanLimitReached;
use App\Domains\Credits\Services\CreditEstimator;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Credits\Services\PlanLimitService;
use App\Domains\Favorites\Services\FavoriteService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Generations\Models\Generation;
use App\Domains\Generations\Requests\BulkGenerationRequest;
use App\Domains\Generations\Requests\StoreGenerationRequest;
use App\Domains\Generations\Services\RetryGeneration;
use App\Domains\Media\Services\ImageOptimizer;
use App\Domains\Products\Models\Product;
use App\Domains\Search\Services\SearchService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Http\HttpCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerationController extends Controller
{
    use ApiResponse;

    public function index(Request $request, FavoriteService $favorites, SearchService $search): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:image,video'],
            'status' => ['nullable', 'in:queued,processing,completed,failed,cancelled'],
            'favorite' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'type.in' => 'نوع خروجی باید تصویر یا ویدیو باشد.',
            'status.in' => 'وضعیت انتخاب‌شده نامعتبر است.',
            'favorite.boolean' => 'فیلتر موردعلاقه نامعتبر است.',
            'search.max' => 'عبارت جستجو حداکثر ۱۰۰ کاراکتر است.',
            'from.date' => 'تاریخ شروع فیلتر نامعتبر است.',
            'to.date' => 'تاریخ پایان فیلتر نامعتبر است.',
            'to.after_or_equal' => 'تاریخ پایان باید بعد یا مساوی تاریخ شروع باشد.',
            'per_page.integer' => 'تعداد در صفحه باید یک عدد باشد.',
            'per_page.min' => 'حداقل تعداد در صفحه ۱ است.',
            'per_page.max' => 'حداکثر تعداد در صفحه ۵۰ است.',
        ]);

        $generations = $request->user()->generations()
            ->with(['creativeProject.product', 'outputMedia'])
            ->when($data['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['favorite'] ?? false, fn ($query) => $query->whereIn('generations.id', $favorites->ids($request->user(), 'generation')))
            ->when($data['search'] ?? '', fn ($query, $searchTerm) => $query->whereIn('generations.id', $search->generationIds($request->user(), $searchTerm)))
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($data['per_page'] ?? 15);

        $favorites->mark($generations->items(), $request->user(), 'generation');

        return $this->success($generations);
    }

    public function store(StoreGenerationRequest $request, CreativeEngine $engine, PromptModerator $moderator, CreditEstimator $estimator, CreditService $credits, PlanLimitService $limits): JsonResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));
        abort_unless($product->user_id === $request->user()->id, 404);
        $data = $request->validated();
        $format = CreativeFormat::from($data['format']);
        try {
            $limits->ensureCanGenerate($request->user(), $format->type());
        } catch (PlanLimitReached $exception) {
            return $this->failure('PLAN_LIMIT_REACHED', $exception->getMessage(), 402);
        }
        $brief = $engine->brief($product, $data, $request->user()->brandKit);
        $prompt = $engine->prompt($brief, $format);
        abort_unless($moderator->passes($prompt), 422, 'درخواست با سیاست محتوایی سازگار نیست.');

        try {
            $generation = DB::transaction(function () use ($request, $product, $data, $brief, $prompt, $format, $limits, $credits, $estimator): Generation {
                $limits->ensureCanGenerateLocked($request->user(), $format->type());
                $project = $request->user()->creativeProjects()->create([...$data, 'product_id' => $product->id, 'brief' => $brief, 'prompt' => $prompt]);
                $generation = $project->generations()->create(['user_id' => $request->user()->id, 'type' => $format->type(), 'status' => 'queued', 'prompt_hash' => hash('sha256', $prompt), 'metadata' => ['aspect_ratio' => $format->aspectRatio()]]);
                $credits->reserve($request->user(), $generation, $estimator->estimate($format->type(), $data['video_duration_seconds'] ?? null));

                return $generation;
            });
        } catch (PlanLimitReached $exception) {
            return $this->failure('PLAN_LIMIT_REACHED', $exception->getMessage(), 402);
        } catch (InsufficientCredits $exception) {
            return $this->failure('INSUFFICIENT_CREDITS', $exception->getMessage(), 402);
        }

        ProcessGeneration::dispatch($generation->id)->onQueue('generations');

        return $this->success($generation->load('creativeProject'), 202);
    }

    /**
     * Bulk catalog processing: queue a generation for every selected product
     * with one shared preset. Missing settings fall back to autoBest() per
     * product, the total cost is checked before anything is created and the
     * loop keeps partial results when a plan/credit limit trips mid-batch.
     */
    public function bulk(BulkGenerationRequest $request, CreativeEngine $engine, PromptModerator $moderator, CreditEstimator $estimator, CreditService $credits, PlanLimitService $limits): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $requestedIds = array_map('intval', (array) $validated['product_ids']);
        unset($validated['product_ids']);

        $products = $user->products()->whereKey($requestedIds)->get();
        if ($products->count() !== count($requestedIds)) {
            return $this->failure('INVALID_PRODUCTS', 'برخی محصولات انتخاب‌شده یافت نشدند.', 422);
        }

        // Only these keys exist as creative_projects columns; everything else
        // from autoBest (aspect_ratio, ...) stays out of the insert.
        $persistable = ['goal', 'style', 'format', 'environment', 'video_duration_seconds', 'custom_prompt', 'surface', 'props', 'camera_angle', 'lighting_setup'];
        $provided = array_filter($validated, fn ($value): bool => $value !== null && $value !== '');
        $goal = CreativeGoal::from($provided['goal'] ?? CreativeGoal::Introduction->value);

        $plans = $products->map(function (Product $product) use ($engine, $provided, $goal, $persistable): array {
            $settings = array_merge($engine->autoBest($product, $goal), $provided);
            $format = CreativeFormat::from($settings['format']);

            return [
                'product' => $product,
                'settings' => $settings,
                'db_settings' => array_intersect_key($settings, array_flip($persistable)),
                'format' => $format,
                'type' => $format->type(),
                'duration' => $settings['video_duration_seconds'] ?? null,
            ];
        });

        $totalCost = (int) $plans->sum(fn (array $plan): int => $estimator->estimate($plan['type'], $plan['duration']));
        $balance = $credits->account($user)->balance;
        if ($totalCost > $balance) {
            return $this->failure(
                'INSUFFICIENT_CREDITS',
                sprintf('پردازش %d محصول به %d Credit نیاز دارد؛ موجودی شما %d Credit است.', $plans->count(), $totalCost, $balance),
                402
            );
        }

        $created = [];
        $skipped = [];
        $reason = null;

        foreach ($plans as $plan) {
            $brief = $engine->brief($plan['product'], $plan['settings'], $user->brandKit);
            $prompt = $engine->prompt($brief, $plan['format']);

            if (! $moderator->passes($prompt)) {
                $reason ??= 'MODERATION_REJECTED';
                $skipped[] = $plan['product']->id;

                continue;
            }

            try {
                $generation = DB::transaction(function () use ($user, $plan, $limits, $credits, $estimator, $brief, $prompt): Generation {
                    $limits->ensureCanGenerateLocked($user, $plan['type']);
                    $project = $user->creativeProjects()->create([
                        ...$plan['db_settings'],
                        'product_id' => $plan['product']->id,
                        'brief' => $brief,
                        'prompt' => $prompt,
                    ]);
                    $generation = $project->generations()->create([
                        'user_id' => $user->id,
                        'type' => $plan['type'],
                        'status' => 'queued',
                        'prompt_hash' => hash('sha256', $prompt),
                        'metadata' => ['aspect_ratio' => $plan['format']->aspectRatio(), 'bulk' => true],
                    ]);
                    $credits->reserve($user, $generation, $estimator->estimate($plan['type'], $plan['duration']));

                    return $generation;
                });
            } catch (PlanLimitReached) {
                $reason ??= 'PLAN_LIMIT_REACHED';
                $skipped[] = $plan['product']->id;

                continue;
            } catch (InsufficientCredits) {
                $reason ??= 'INSUFFICIENT_CREDITS';
                $skipped[] = $plan['product']->id;

                continue;
            }

            ProcessGeneration::dispatch($generation->id)->onQueue('generations');
            $created[] = $generation->id;
        }

        if ($created === []) {
            return $this->failure($reason ?? 'BULK_FAILED', 'هیچ محصولی پردازش نشد.', 422);
        }

        return $this->success([
            'created' => count($created),
            'generation_ids' => $created,
            'skipped' => count($skipped),
            'skipped_product_ids' => $skipped,
            'reason' => $reason,
        ], 202);
    }

    public function show(Request $request, Generation $generation): JsonResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);

        return $this->success($generation->load(['creativeProject.product', 'outputMedia']));
    }

    public function retry(Request $request, Generation $generation, RetryGeneration $retry): JsonResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);
        try {
            $generation = $retry->execute($request->user(), $generation->id);
        } catch (PlanLimitReached $exception) {
            return $this->failure('PLAN_LIMIT_REACHED', $exception->getMessage(), 402);
        } catch (InsufficientCredits $exception) {
            return $this->failure('INSUFFICIENT_CREDITS', $exception->getMessage(), 402);
        }
        ProcessGeneration::dispatch($generation->id)->onQueue('generations');

        return $this->success($generation, 202);
    }

    public function regenerate(Request $request, Generation $generation, CreditEstimator $estimator, CreditService $credits, PlanLimitService $limits): JsonResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);
        abort_unless($generation->status === 'completed', 409, 'فقط تولید تکمیل‌شده قابل تولید مجدد است.');

        $project = $generation->creativeProject;
        try {
            $limits->ensureCanGenerate($request->user(), $generation->type);
        } catch (PlanLimitReached $exception) {
            return $this->failure('PLAN_LIMIT_REACHED', $exception->getMessage(), 402);
        }

        try {
            $newGeneration = DB::transaction(function () use ($request, $generation, $project, $limits, $credits, $estimator): Generation {
                $limits->ensureCanGenerateLocked($request->user(), $generation->type);
                $newGeneration = $project->generations()->create([
                    'user_id' => $request->user()->id,
                    'type' => $generation->type,
                    'status' => 'queued',
                    'prompt_hash' => $generation->prompt_hash,
                    'metadata' => $generation->metadata,
                ]);
                $credits->reserve($request->user(), $newGeneration, $estimator->estimate($generation->type, $project->video_duration_seconds));

                return $newGeneration;
            });
        } catch (PlanLimitReached $exception) {
            return $this->failure('PLAN_LIMIT_REACHED', $exception->getMessage(), 402);
        } catch (InsufficientCredits $exception) {
            return $this->failure('INSUFFICIENT_CREDITS', $exception->getMessage(), 402);
        }

        ProcessGeneration::dispatch($newGeneration->id)->onQueue('generations');

        return $this->success($newGeneration->load('creativeProject'), 202);
    }

    public function feedback(Request $request, Generation $generation): JsonResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);
        abort_unless($generation->status === 'completed', 409, 'فقط تولید تکمیل‌شده قابل ارزیابی است.');

        $data = $request->validate([
            'feedback' => ['required', 'string', 'in:positive,negative'],
        ], [
            'feedback.required' => 'ارسال مقدار بازخورد الزامی است.',
            'feedback.in' => 'بازخورد باید یکی از مقادیر مثبت (positive) یا منفی (negative) باشد.',
        ]);
        $generation->update(['feedback' => $data['feedback']]);

        return $this->success($generation->fresh());
    }

    public function download(Request $request, Generation $generation, ImageOptimizer $optimizer)
    {
        abort_unless($generation->user_id === $request->user()->id, 404);
        abort_unless($generation->status === 'completed' && $generation->outputMedia, 404);

        $media = $generation->outputMedia;
        $path = $media->path;
        $mime = $media->mime;

        if ((string) $request->query('variant') === 'web') {
            // Screen-sized WebP for preview/download; non-image outputs
            // (videos, undecodable files) fall back to the original.
            $webPath = $optimizer->webVariantFor($media);
            if ($webPath !== null) {
                $path = $webPath;
                $mime = 'image/webp';
            }
        }

        $etag = HttpCache::etag($media->getKey(), $path, $media->updated_at?->getTimestamp() ?? 0);
        $cacheHeaders = ['Cache-Control' => 'private, max-age=86400', 'ETag' => $etag];
        if (HttpCache::notModified($request, $etag)) {
            return response('', 304, $cacheHeaders);
        }

        return response()->streamDownload(function () use ($media, $path): void {
            $stream = Storage::disk($media->disk)->readStream($path);
            abort_unless(is_resource($stream), 404);
            fpassthru($stream);
            fclose($stream);
        }, "generation-{$generation->id}.{$this->extension($mime)}", ['Content-Type' => $mime] + $cacheHeaders);
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            default => 'jpg',
        };
    }
}
