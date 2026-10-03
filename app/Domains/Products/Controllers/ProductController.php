<?php

namespace App\Domains\Products\Controllers;

use App\Domains\Favorites\Services\FavoriteService;
use App\Domains\Media\Services\MediaUploadService;
use App\Domains\Products\Models\Product;
use App\Domains\Products\Requests\StoreProductRequest;
use App\Domains\Products\Requests\UpdateProductRequest;
use App\Domains\Search\Services\SearchIndexer;
use App\Domains\Search\Services\SearchService;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(Request $request, FavoriteService $favorites, SearchService $search): JsonResponse
    {
        $products = $request->user()->products()
            ->with(['assets' => fn ($query) => $query->wherePivot('is_primary', true)])
            ->when($request->filled('search'), fn ($query) => $query->whereIn('products.id', $search->productIds($request->user(), (string) $request->string('search'))))
            ->when($request->boolean('favorite'), fn ($query) => $query->whereIn('products.id', $favorites->ids($request->user(), 'product')))
            ->latest()
            ->paginate(min($request->integer('per_page', 15), 50));

        $favorites->mark($products->items(), $request->user(), 'product');

        return $this->success($products);
    }

    public function store(StoreProductRequest $request, SearchIndexer $indexer): JsonResponse
    {
        $product = $request->user()->products()->create($request->validated());
        $indexer->indexProduct($product);

        return $this->success($product, 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->ensureOwner($request, $product);

        return $this->success($product->load('assets'));
    }

    public function update(UpdateProductRequest $request, Product $product, SearchIndexer $indexer): JsonResponse
    {
        $this->ensureOwner($request, $product);
        $product->update($request->validated());
        $indexer->indexProduct($product);

        return $this->success($product->fresh('assets'));
    }

    public function destroy(Request $request, Product $product, MediaUploadService $media, FavoriteService $favorites, SearchIndexer $indexer): JsonResponse
    {
        $this->ensureOwner($request, $product);
        $assets = $product->assets()->get();
        $product->assets()->detach();
        $product->delete();
        $favorites->forget($request->user(), 'product', (int) $product->getKey());
        $indexer->removeProduct((int) $product->getKey());

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
