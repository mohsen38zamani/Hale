<?php

namespace App\Domains\Search\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class DatabaseSearchDriver implements SearchDriver
{
    public function productIds(User $user, string $query): ?array
    {
        $term = $this->likeTerm($query);

        return $this->ids($user->products()
            ->where(function (Builder $builder) use ($term): void {
                $builder->whereRaw("name like ? escape '!'", [$term])
                    ->orWhereRaw("description like ? escape '!'", [$term]);
            }));
    }

    public function generationIds(User $user, string $query): ?array
    {
        $term = $this->likeTerm($query);

        return $this->ids($user->generations()
            ->where(function (Builder $builder) use ($term): void {
                $builder->whereHas('creativeProject.product', fn (Builder $product) => $product->whereRaw("name like ? escape '!'", [$term]))
                    ->orWhereHas('creativeProject', fn (Builder $project) => $project->whereRaw("prompt like ? escape '!'", [$term]));
            }));
    }

    /**
     * Escape LIKE metacharacters with a portable escape character ('!' works
     * identically on MySQL and SQLite, unlike backslashes).
     */
    private function likeTerm(string $query): string
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($query));

        return "%{$escaped}%";
    }

    /**
     * @return array<int, int>
     */
    private function ids(Builder|Relation $builder): array
    {
        return $builder->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }
}
