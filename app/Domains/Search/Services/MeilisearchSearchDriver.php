<?php

namespace App\Domains\Search\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class MeilisearchSearchDriver implements SearchDriver
{
    public function productIds(User $user, string $query): ?array
    {
        return $this->search('products', $user, $query);
    }

    public function generationIds(User $user, string $query): ?array
    {
        return $this->search('generations', $user, $query);
    }

    /**
     * Ask Meilisearch for matching document ids scoped to the user.
     * Returns null on any transport/API failure so the caller can fall
     * back to the database driver instead of breaking search.
     *
     * @return array<int, int>|null
     */
    private function search(string $index, User $user, string $query): ?array
    {
        $config = config('search.meilisearch');

        try {
            $response = Http::withToken((string) ($config['key'] ?? ''))
                ->timeout((int) ($config['timeout'] ?? 3))
                ->post(rtrim((string) $config['host'], '/').'/indexes/'.rawurlencode((string) $config['indexes'][$index]).'/search', [
                    'q' => $query,
                    'filter' => 'user_id = '.$user->getKey(),
                    'limit' => 500,
                ]);
        } catch (Throwable $exception) {
            Log::warning('search.meilisearch_unavailable', ['index' => $index, 'error' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('search.meilisearch_error', ['index' => $index, 'status' => $response->status()]);

            return null;
        }

        $ids = [];
        foreach ($response->json('hits', []) as $hit) {
            if (isset($hit['id'])) {
                $ids[] = (int) $hit['id'];
            }
        }

        return $ids;
    }
}
