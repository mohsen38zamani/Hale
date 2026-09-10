<?php

namespace App\Domains\Media\Controllers;

use App\Domains\Media\Requests\UploadProductAssetRequest;
use App\Domains\Media\Services\MediaUploadService;
use App\Domains\Products\Models\Product;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductAssetController extends Controller
{
    use ApiResponse;

    public function store(UploadProductAssetRequest $request, Product $product, MediaUploadService $uploader): JsonResponse
    {
        abort_unless($product->user_id === $request->user()->id, 404);

        $asset = $uploader->storeProductImage($request->user(), $request->file('image'));
        DB::table('product_assets')->where('product_id', $product->id)->update(['is_primary' => false]);
        $product->assets()->attach($asset->id, ['is_primary' => true]);

        return $this->success($asset, 201);
    }
}
