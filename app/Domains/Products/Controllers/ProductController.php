<?php

namespace App\Domains\Products\Controllers;

use App\Domains\Media\Services\MediaUploadService;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Requests\StoreProductRequest;
use App\Domains\Products\Requests\UpdateProductRequest;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $products = $request->user()->products()
            ->with(['assets' => fn ($query) => $query->wherePivot('is_primary', true)])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 50));

        return $this->success($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $request->user()->products()->create($request->validated());

        return $this->success($product, 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->ensureOwner($request, $product);

        return $this->success($product->load('assets'));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->ensureOwner($request, $product);
        $product->update($request->validated());

        return $this->success($product->fresh('assets'));
    }

    public function destroy(Request $request, Product $product, MediaUploadService $media): JsonResponse
    {
        $this->ensureOwner($request, $product);
        $assets = $product->assets()->get();
        $product->assets()->detach();
        $product->delete();

        foreach ($assets as $asset) {
            if (! $asset->products()->exists()) {
                $media->delete($asset);
            }
        }

        return $this->success(['message' => 'محصول حذف شد.']);
    }

    private function ensureOwner(Request $request, Product $product): void
    {
        abort_unless($product->user_id === $request->user()->id, 404);
    }
}
