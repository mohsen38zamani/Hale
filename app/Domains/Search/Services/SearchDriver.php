<?php

namespace App\Domains\Search\Services;

use App\Models\User;

interface SearchDriver
{
    /**
     * Ids of the user's products matching the free-text query.
     *
     * @return array<int, int>|null null means "driver unavailable, fall back"
     */
    public function productIds(User $user, string $query): ?array;

    /**
     * Ids of the user's generations matching the free-text query.
     *
     * @return array<int, int>|null null means "driver unavailable, fall back"
     */
    public function generationIds(User $user, string $query): ?array;
}
