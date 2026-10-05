<?php

namespace App\Domains\Editing\Controllers;

use App\Domains\Credits\Exceptions\InsufficientCredits;
use App\Domains\Credits\Services\CreditEstimator;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Editing\Exceptions\SourceNotFoundException;
use App\Domains\Editing\Exceptions\SourceNotProcessableException;
use App\Domains\Editing\Jobs\ProcessImageEdit;
use App\Domains\Editing\Models\ImageEdit;
use App\Domains\Editing\Requests\StoreImageEditRequest;
use App\Domains\Editing\Services\EditSourceResolver;
use App\Domains\Media\Services\ImageOptimizer;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Http\HttpCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImageEditController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_type' => ['nullable', 'string', 'in:generation,product'],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:queued,processing,completed,failed'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'source_type.in' => 'منبع ویرایش معتبر نیست.',
            'status.in' => 'وضعیت ویرایش معتبر نیست.',
        ]);

        $query = $request->user()->imageEdits()->with('outputMedia')->latest();
        if (isset($data['source_type'])) {
            $query->where('source_type', $data['source_type']);
        }
        if (isset($data['source_id'])) {
            $query->where('source_id', $data['source_id']);
        }
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return $this->success($query->paginate($data['per_page'] ?? 15));
    }

    /**
     * Create an edit run: resolve the source first (404/422 before any
     * money moves), apply the plan gate, then charge + enqueue atomically
     * so a rolled-back transaction never leaves a charged orphan row.
     */
    public function store(StoreImageEditRequest $request, EditSourceResolver $resolver, CreditEstimator $estimator, CreditService $credits): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $operation = (string) $data['operation'];
        $target = $data['target'] ?? 'hd';

        try {
            $resolver->resolve((string) $data['source_type'], (int) $data['source_id'], $user);
        } catch (SourceNotFoundException $exception) {
            return $this->failure('SOURCE_NOT_FOUND', $exception->getMessage(), 404);
        } catch (SourceNotProcessableException $exception) {
            return $this->failure('SOURCE_NOT_PROCESSABLE', $exception->getMessage(), 422);
        }

        // Upscaling past HD mirrors the premium quality gate.
        $maxQuality = (string) config('plans.'.($user->plan_key ?: 'free').'.quality', 'standard');
        if ($operation === 'upscale' && in_array($target, ['2k', '4k'], true) && $maxQuality !== 'premium') {
            return $this->failure('PREMIUM_QUALITY_REQUIRED', 'ارتقای تصویر به ۲K/۴K فقط در پلن پرمیوم فعال است.', 403);
        }

        $cost = $estimator->estimateEdit($operation, $target);
        if ($credits->account($user)->balance < $cost) {
            return $this->failure('INSUFFICIENT_CREDITS', 'اعتبار کافی نیست.', 402);
        }

        $options = array_filter([
            'background' => $data['background'] ?? null,
            'target' => $operation === 'upscale' ? $target : null,
            'aspect_ratio' => $data['aspect_ratio'] ?? null,
            'effect' => $data['effect'] ?? null,
        ], fn ($value) => $value !== null);

        try {
            $edit = DB::transaction(function () use ($user, $data, $operation, $options, $cost, $credits): ImageEdit {
                $edit = $user->imageEdits()->create([
                    'source_type' => $data['source_type'],
                    'source_id' => $data['source_id'],
                    'operation' => $operation,
                    'options' => $options,
                    'status' => 'queued',
                    'credits_spent' => $cost,
                ]);

                // One-shot charge, idempotent on the edit id; refunded
                // automatically by the job's terminal failure handler.
                $credits->spendForTask($user, null, $cost, "edit:{$edit->id}", ['edit_id' => $edit->id, 'operation' => $operation], 'edit');

                return $edit;
            });
        } catch (InsufficientCredits $exception) {
            return $this->failure('INSUFFICIENT_CREDITS', $exception->getMessage(), 402);
        }

        ProcessImageEdit::dispatch($edit->id)->onQueue('generations');

        // fresh(): under the sync queue the job already ran inline, so the
        // response must reflect the settled status, not the creation one.
        return $this->success($edit->fresh(), 202);
    }

    public function show(Request $request, ImageEdit $edit): JsonResponse
    {
        abort_unless($edit->user_id === $request->user()->id, 404);

        return $this->success($edit->load('outputMedia'));
    }

    /**
     * Public price list + plan capabilities for the tools modal, so the UI
     * can render costs and lock premium targets without guessing.
     */
    public function costs(Request $request, CreditEstimator $estimator, CreditService $credits): JsonResponse
    {
        $user = $request->user();
        $planKey = (string) ($user->plan_key ?: 'free');
        $quality = (string) config("plans.{$planKey}.quality", 'standard');

        return $this->success([
            'costs' => [
                'remove_bg' => $estimator->estimateEdit('remove_bg'),
                'shadow' => $estimator->estimateEdit('shadow'),
                'expand' => $estimator->estimateEdit('expand'),
                'upscale' => [
                    'hd' => $estimator->estimateEdit('upscale', 'hd'),
                    '2k' => $estimator->estimateEdit('upscale', '2k'),
                    '4k' => $estimator->estimateEdit('upscale', '4k'),
                ],
            ],
            'balance' => $credits->account($user)->balance,
            'plan' => [
                'key' => $planKey,
                'quality' => $quality,
                'max_upscale_target' => $quality === 'premium' ? '4k' : 'hd',
            ],
        ]);
    }

    public function download(Request $request, ImageEdit $edit, ImageOptimizer $optimizer)
    {
        abort_unless($edit->user_id === $request->user()->id, 404);
        abort_unless($edit->status === 'completed' && $edit->outputMedia, 404);

        $media = $edit->outputMedia;
        $path = $media->path;
        $mime = $media->mime;

        if ((string) $request->query('variant') === 'web') {
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
        }, "edit-{$edit->id}.{$this->extension((string) $mime)}", ['Content-Type' => (string) $mime] + $cacheHeaders);
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
