<?php

namespace App\Domains\Favorites\Services;

use App\Domains\Favorites\Models\Favorite;
use App\Domains\Generations\Models\Generation;
use App\Domains\Products\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class FavoriteService
{
    /**
     * Favorite a target for a user, or remove the existing favorite.
     *
     * @return bool the new favorite state (true = favorited)
     */
    public function toggle(User $user, string $type, int $id): bool
    {
        $existing = Favorite::query()
            ->where('user_id', $user->getKey())
            ->where('favoritable_type', $this->morphClass($type))
            ->where('favoritable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        try {
            Favorite::query()->create([
                'user_id' => $user->getKey(),
                'favoritable_type' => $this->morphClass($type),
                'favoritable_id' => $id,
            ]);
        } catch (QueryException) {
            // Lost a race against a concurrent toggle: the row now exists,
            // which means the authoritative state is "favorited".
            return true;
        }

        return true;
    }

    /**
     * Favorited target ids of the given type for the user.
     *
     * @return array<int, int>
     */
    public function ids(User $user, string $type): array
    {
        return Favorite::query()
            ->where('user_id', $user->getKey())
            ->where('favoritable_type', $this->morphClass($type))
            ->pluck('favoritable_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Flag each item with an `is_favorite` attribute for the current user.
     *
     * @param  iterable<int, Model>  $items
     */
    public function mark(iterable $items, User $user, string $type): void
    {
        $keys = [];
        foreach ($items as $item) {
            $keys[] = $item->getKey();
        }

        if ($keys === []) {
            return;
        }

        $favIds = array_flip(
            Favorite::query()
                ->where('user_id', $user->getKey())
                ->where('favoritable_type', $this->morphClass($type))
                ->whereIn('favoritable_id', $keys)
                ->pluck('favoritable_id')
                ->map(fn ($id): int => (int) $id)
                ->all()
        );

        foreach ($items as $item) {
            $item->setAttribute('is_favorite', isset($favIds[$item->getKey()]));
        }
    }

    /**
     * Drop favorite rows pointing at a deleted target (morph, no FK).
     */
    public function forget(User $user, string $type, int $id): void
    {
        Favorite::query()
            ->where('user_id', $user->getKey())
            ->where('favoritable_type', $this->morphClass($type))
            ->where('favoritable_id', $id)
            ->delete();
    }

    /**
     * Eloquent query for the models a favorite type may point at.
     *
     * @return Builder<Model>
     */
    public function queryFor(string $type): Builder
    {
        return match ($type) {
            'product' => Product::query(),
            'generation' => Generation::query(),
            default => abort(422, 'نوع موردعلاقه نامعتبر است.'),
        };
    }

    private function morphClass(string $type): string
    {
        return match ($type) {
            'product' => Product::class,
            'generation' => Generation::class,
            default => abort(422, 'نوع موردعلاقه نامعتبر است.'),
        };
    }
}
