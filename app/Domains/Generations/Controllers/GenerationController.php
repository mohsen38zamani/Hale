<?php

namespace App\Domains\Generations\Controllers;

use App\Domains\AI\Services\PromptModerator;
use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Services\CreativeEngine;
use App\Domains\Credits\Exceptions\InsufficientCredits;
use App\Domains\Credits\Exceptions\PlanLimitReached;
use App\Domains\Credits\Services\CreditEstimator;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Credits\Services\PlanLimitService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Generations\Models\Generation;
use App\Domains\Generations\Requests\StoreGenerationRequest;
use App\Domains\Generations\Services\RetryGeneration;
use App\Domains\Products\Models\Product;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:image,video'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'type.in' => 'نوع خروجی باید تصویر یا ویدیو باشد.',
            'from.date' => 'تاریخ شروع فیلتر نامعتبر است.',
            'to.date' => 'تاریخ پایان فیلتر نامعتبر است.',
            'to.after_or_equal' => 'تاریخ پایان باید بعد یا مساوی تاریخ شروع باشد.',
            'per_page.integer' => 'تعداد در صفحه باید یک عدد باشد.',
            'per_page.min' => 'حداقل تعداد در صفحه ۱ است.',
            'per_page.max' => 'حداکثر تعداد در صفحه ۵۰ است.',
        ]);

        return $this->success($request->user()->generations()
            ->with(['creativeProject.product', 'outputMedia'])
            ->when($data['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($data['per_page'] ?? 15));
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
        $brief = $engine->brief($product, $data);
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

    public function download(Request $request, Generation $generation)
    {
        abort_unless($generation->user_id === $request->user()->id, 404);
        abort_unless($generation->status === 'completed' && $generation->outputMedia, 404);

        $media = $generation->outputMedia;

        return response()->streamDownload(function () use ($media): void {
            $stream = Storage::disk($media->disk)->readStream($media->path);
            fpassthru($stream);
            fclose($stream);
        }, "generation-{$generation->id}.{$this->extension($media->mime)}", ['Content-Type' => $media->mime]);
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'video/mp4' => 'mp4',
            default => 'jpg',
        };
    }
}
