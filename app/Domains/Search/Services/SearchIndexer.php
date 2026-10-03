<?php

namespace App\Domains\Search\Services;

use App\Domains\Generations\Models\Generation;
use App\Domains\Products\Models\Product;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Write path for the Meilisearch indexes. Every method is best-effort:
 * indexing failures are logged and never break the main product or
 * generation flow. With SEARCH_DRIVER=database the index is unused but
 * can still be kept warm via `php artisan search:reindex`.
 */
class SearchIndexer
{
    public function indexProduct(Product $product): void
    {
        $this->push('products', [$this->productDocument($product)]);
    }

    public function removeProduct(int $productId): void
    {
        $this->delete('products', $productId);
    }

    public function indexGeneration(Generation $generation): void
    {
        $this->push('generations', [$this->generationDocument($generation)]);
    }

    public function pushProducts(iterable $products): void
    {
        $documents = [];
        foreach ($products as $product) {
            $documents[] = $this->productDocument($product);
        }
        if ($documents !== []) {
            $this->push('products', $documents);
        }
    }

    public function pushGenerations(iterable $generations): void
    {
        $documents = [];
        foreach ($generations as $generation) {
            $documents[] = $this->generationDocument($generation);
        }
        if ($documents !== []) {
            $this->push('generations', $documents);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     */
    private function push(string $index, array $documents): void
    {
        try {
            $response = $this->request()
                ->post($this->indexUrl($index).'/documents', $documents);

            if (! $response->successful()) {
                Log::warning('search.index_push_failed', ['index' => $index, 'status' => $response->status()]);
            }
        } catch (Throwable $exception) {
            Log::warning('search.index_push_failed', ['index' => $index, 'error' => $exception->getMessage()]);
        }
    }

    private function delete(string $index, int $documentId): void
    {
        try {
            $response = $this->request()->delete($this->indexUrl($index).'/documents/'.$documentId);

            if (! $response->successful()) {
                Log::warning('search.index_delete_failed', ['index' => $index, 'status' => $response->status()]);
            }
        } catch (Throwable $exception) {
            Log::warning('search.index_delete_failed', ['index' => $index, 'error' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function productDocument(Product $product): array
    {
        return [
            'id' => $product->getKey(),
            'user_id' => $product->user_id,
            'name' => (string) $product->name,
            'description' => (string) ($product->description ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generationDocument(Generation $generation): array
    {
        $generation->loadMissing('creativeProject.product');

        return [
            'id' => $generation->getKey(),
            'user_id' => $generation->user_id,
            'type' => $generation->type,
            'status' => $generation->status,
            'product_name' => (string) ($generation->creativeProject?->product?->name ?? ''),
            'prompt' => (string) ($generation->creativeProject?->prompt ?? ''),
            'created_at' => $generation->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return PendingRequest
     */
    private function request()
    {
        $config = config('search.meilisearch');

        return Http::withToken((string) ($config['key'] ?? ''))
            ->timeout((int) ($config['timeout'] ?? 3));
    }

    private function indexUrl(string $index): string
    {
        $config = config('search.meilisearch');

        return rtrim((string) $config['host'], '/').'/indexes/'.rawurlencode((string) $config['indexes'][$index]);
    }
}
