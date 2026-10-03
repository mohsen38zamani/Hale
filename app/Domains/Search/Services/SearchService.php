<?php

namespace App\Domains\Search\Services;

use App\Models\User;

class SearchService
{
    public function __construct(
        private readonly DatabaseSearchDriver $database,
        private readonly MeilisearchSearchDriver $meilisearch,
    ) {}

    /**
     * @return array<int, int>
     */
    public function productIds(User $user, string $query): array
    {
        return $this->driver()->productIds($user, $query) ?? $this->database->productIds($user, $query) ?? [];
    }

    /**
     * @return array<int, int>
     */
    public function generationIds(User $user, string $query): array
    {
        return $this->driver()->generationIds($user, $query) ?? $this->database->generationIds($user, $query) ?? [];
    }

    private function driver(): SearchDriver
    {
        $configured = (string) config('search.driver');
        $host = (string) config('search.meilisearch.host');

        if ($configured === 'meilisearch' && $host !== '') {
            return $this->meilisearch;
        }

        return $this->database;
    }
}
