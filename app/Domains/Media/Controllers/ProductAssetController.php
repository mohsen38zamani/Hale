<?php

namespace App\Domains\Media\Controllers;

use App\Domains\Media\Models\MediaAsset;
use App\Domains\Media\Requests\UploadProductAssetRequest;
use App\Domains\Media\Services\ImageOptimizer;
use App\Domains\Media\Services\MediaUploadService;
use App\Domains\Products\Models\Product;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use App\Support\Http\HttpCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductAssetController extends Controller
{
    use ApiResponse;

    public function store(UploadProductAssetRequest $request, Product $product, MediaUploadService $uploader): JsonResponse
    {
        abort_unless($product->user_id === $request->user()->id, 404);

        $asset = $uploader->storeProductImage($request->user(), $request->file('image'));
        try {
            DB::transaction(function () use ($product, $asset): void {
                DB::table('product_assets')->where('product_id', $product->id)->update(['is_primary' => false]);
                $product->assets()->attach($asset->id, ['is_primary' => true]);
            });
        } catch (Throwable $exception) {
            $uploader->delete($asset);
            throw $exception;
        }

        return $this->success($asset, 201);
    }

    public function destroy(Product $product, MediaAsset $asset, MediaUploadService $uploader): JsonResponse
    {
        abort_unless($product->user_id === request()->user()->id, 404);

        $pivot = $product->assets()->whereKey($asset->id)->first()?->pivot;
        abort_unless($pivot !== null, 404);

        $wasPrimary = (bool) $pivot->is_primary;
        $product->assets()->detach($asset->id);

        if ($wasPrimary) {
            $replacement = $product->assets()->latest('media_assets.id')->first();
            if ($replacement !== null) {
                $product->assets()->updateExistingPivot($replacement->id, ['is_primary' => true]);
            }
        }

        if ($asset->products()->doesntExist()) {
            $uploader->delete($asset);
        }

        return $this->success(['message' => 'رسانه با موفقیت حذف شد.']);
    }

    public function download(Request $request, Product $product, MediaAsset $asset, ImageOptimizer $optimizer)
    {
        abort_unless($product->user_id === $request->user()->id, 404);
        abort_unless($product->assets()->whereKey($asset->id)->exists(), 404);

        $variant = (string) $request->query('variant', 'default');
        $mime = $asset->mime;

        if ($variant === 'original') {
            $path = $asset->path;
        } elseif ($variant === 'web') {
            // Screen-sized WebP, created on the first request; falls back to
            // the untouched original when no variant can be produced.
            $path = $asset->path;
            $webPath = $optimizer->webVariantFor($asset);
            if ($webPath !== null) {
                $path = $webPath;
                $mime = 'image/webp';
            }
        } else {
            $path = $asset->thumbnail_path ?? $asset->path;
            $mime = $asset->thumbnail_path !== null ? 'image/webp' : $asset->mime;
        }

        $etag = HttpCache::etag($asset->getKey(), $path, $asset->updated_at?->getTimestamp() ?? 0);
        $cacheHeaders = ['Cache-Control' => 'private, max-age=86400', 'ETag' => $etag];
        if (HttpCache::notModified($request, $etag)) {
            return response('', 304, $cacheHeaders);
        }

        return response()->stream(function () use ($asset, $path): void {
            $stream = Storage::disk($asset->disk)->readStream($path);
            abort_unless(is_resource($stream), 404);
            fpassthru($stream);
            fclose($stream);
        }, 200, ['Content-Type' => $mime] + $cacheHeaders);
    }
}
