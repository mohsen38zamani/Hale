<?php

namespace App\Domains\Generations\Controllers;

use App\Domains\AI\Services\PromptModerator;
use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Services\CreativeEngine;
use App\Domains\Credits\Exceptions\InsufficientCredits;
use App\Domains\Credits\Services\CreditEstimator;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Generations\Models\Generation;
use App\Domains\Generations\Requests\StoreGenerationRequest;
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
        return $this->success($request->user()->generations()->with(['creativeProject.product', 'outputMedia'])->latest()->paginate(min($request->integer('per_page', 15), 50)));
    }

    public function store(StoreGenerationRequest $request, CreativeEngine $engine, PromptModerator $moderator, CreditEstimator $estimator, CreditService $credits): JsonResponse
    {
        $product = Product::query()->findOrFail($request->integer('product_id'));
        abort_unless($product->user_id === $request->user()->id, 404);
        $data = $request->validated();
        $format = CreativeFormat::from($data['format']);
        $brief = $engine->brief($product, $data);
        $prompt = $engine->prompt($brief, $format);
        abort_unless($moderator->passes($prompt), 422, 'درخواست با سیاست محتوایی سازگار نیست.');

        $generation = DB::transaction(function () use ($request, $product, $data, $brief, $prompt, $format): Generation {
            $project = $request->user()->creativeProjects()->create([...$data, 'product_id' => $product->id, 'brief' => $brief, 'prompt' => $prompt]);

            return $project->generations()->create(['user_id' => $request->user()->id, 'type' => $format->type(), 'status' => 'queued', 'prompt_hash' => hash('sha256', $prompt), 'metadata' => ['aspect_ratio' => $format->aspectRatio()]]);
        });

        try {
            $credits->reserve($request->user(), $generation, $estimator->estimate($format->type(), $data['video_duration_seconds'] ?? null));
        } catch (InsufficientCredits $exception) {
            $generation->creativeProject()->delete();

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

    public function retry(Request $request, Generation $generation, CreditEstimator $estimator, CreditService $credits): JsonResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);
        abort_unless($generation->status === 'failed', 409, 'فقط تولید ناموفق قابل تلاش مجدد است.');
        try {
            $credits->reserve($request->user(), $generation, $estimator->estimate($generation->type, $generation->creativeProject->video_duration_seconds));
        } catch (InsufficientCredits $exception) {
            return $this->failure('INSUFFICIENT_CREDITS', $exception->getMessage(), 402);
        }
        $generation->update(['status' => 'queued', 'error_message' => null]);
        ProcessGeneration::dispatch($generation->id)->onQueue('generations');

        return $this->success($generation->fresh(), 202);
    }

    public function feedback(Request $request, Generation $generation): JsonResponse
    {
        abort_unless($generation->user_id === $request->user()->id, 404);
        abort_unless($generation->status === 'completed', 409, 'فقط تولید تکمیل‌شده قابل ارزیابی است.');

        $data = $request->validate(['feedback' => ['required', 'string', 'in:positive,negative']]);
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
