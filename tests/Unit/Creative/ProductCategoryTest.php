<?php

namespace Tests\Unit\Creative;

use App\Domains\Creative\Enums\ProductCategory;
use Tests\TestCase;

class ProductCategoryTest extends TestCase
{
    /** The ten documented trades, in catalogue order. */
    private const TRADES = [
        'jewelry',
        'watches',
        'shoes',
        'perfumes',
        'cosmetics',
        'fashion',
        'bags',
        'electronics',
        'home_decor',
        'general',
    ];

    public function test_the_enum_covers_exactly_the_ten_documented_trades(): void
    {
        $this->assertSame(self::TRADES, array_column(ProductCategory::cases(), 'value'));
    }

    public function test_every_trade_carries_a_label_icon_and_scale_lock(): void
    {
        foreach (ProductCategory::cases() as $category) {
            $this->assertNotSame('', $category->label(), "Trade [{$category->value}] has no Persian label.");
            $this->assertNotSame('', $category->icon(), "Trade [{$category->value}] has no icon.");
            $this->assertNotSame('', $category->defaultScaleInstruction(), "Trade [{$category->value}] has no scale lock.");
        }
    }

    public function test_scale_locks_are_written_for_models_not_readers(): void
    {
        // A scale lock is pasted verbatim into an image model prompt, so it
        // must be Latin-script wording - a Persian clause would either be
        // dropped by the model or, worse, translated by it.
        foreach (ProductCategory::cases() as $category) {
            $this->assertMatchesRegularExpression(
                '/^[\x20-\x7E]+$/',
                $category->defaultScaleInstruction(),
                "Scale lock of [{$category->value}] is not plain English."
            );
        }
    }

    public function test_only_jewelry_framing_is_macro(): void
    {
        $macro = array_values(array_filter(
            ProductCategory::cases(),
            fn (ProductCategory $category) => $category->isMacro()
        ));

        $this->assertSame([ProductCategory::Jewelry->value], array_column($macro, 'value'));
    }

    public function test_general_is_the_only_fallback(): void
    {
        $fallbacks = array_values(array_filter(
            ProductCategory::cases(),
            fn (ProductCategory $category) => $category->isFallback()
        ));

        $this->assertSame([ProductCategory::General->value], array_column($fallbacks, 'value'));
    }

    public function test_protected_features_and_banned_vocabulary_exist_for_every_trade(): void
    {
        foreach (ProductCategory::cases() as $category) {
            $entry = config("creative_categories.categories.{$category->value}");

            $this->assertIsArray($entry, "Catalogue entry for [{$category->value}] is missing.");
            $this->assertNotEmpty($entry['protected_features'], "Trade [{$category->value}] protects nothing.");
            $this->assertIsArray($entry['banned_words'], "Trade [{$category->value}] has no banned-word list.");
        }
    }
}
