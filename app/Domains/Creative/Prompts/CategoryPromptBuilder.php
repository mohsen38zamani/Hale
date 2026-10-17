<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Contracts\CategoryPromptDecoratorInterface;

/**
 * Turns a trade's catalogue entry into prompt language.
 *
 * The wording itself lives in config/creative_categories.php; this builder
 * only assembles it: the scale lock prefixed the way a model reads it, and
 * the protected features folded into one imperative sentence a copied prompt
 * can carry on its own. An absent or unknown trade contributes nothing, so a
 * brief built before the trade studios existed compiles byte for byte the
 * same as it always did.
 */
class CategoryPromptBuilder implements CategoryPromptDecoratorInterface
{
    public function scale(?string $category): string
    {
        $lock = trim((string) ($this->entry($category)['scale_lock'] ?? ''));

        return $lock === '' ? '' : 'Physical scale: '.$lock.'.';
    }

    public function guardrail(?string $category): string
    {
        $features = array_values(array_filter(
            array_map('trim', (array) ($this->entry($category)['protected_features'] ?? [])),
            fn (string $feature): bool => $feature !== ''
        ));

        if ($features === []) {
            return '';
        }

        if (count($features) === 1) {
            return 'Keep '.$features[0].' unchanged.';
        }

        $last = array_pop($features);

        return 'Keep '.implode(', ', $features).' and '.$last.' unchanged.';
    }

    /**
     * @return list<string>
     */
    public function bannedWords(?string $category): array
    {
        return array_values(array_filter(
            array_map('trim', (array) ($this->entry($category)['banned_words'] ?? [])),
            fn (string $word): bool => $word !== ''
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function entry(?string $category): array
    {
        $key = strtolower(trim((string) $category));
        $entry = $key === '' ? null : config("creative_categories.categories.{$key}");

        return is_array($entry) ? $entry : [];
    }
}
