<?php

namespace Tests\Unit\Creative;

use App\Domains\Creative\Prompts\CategoryPromptBuilder;
use Tests\TestCase;

class CategoryPromptBuilderTest extends TestCase
{
    private CategoryPromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new CategoryPromptBuilder;
    }

    public function test_the_scale_clause_pins_the_trade_to_its_real_size(): void
    {
        $scale = $this->builder->scale('jewelry');

        $this->assertStringStartsWith('Physical scale: ', $scale);
        $this->assertStringEndsWith('.', $scale);
        $this->assertStringContainsString('100mm lens', $scale);
    }

    public function test_every_trade_has_a_scale_clause(): void
    {
        foreach (array_keys((array) config('creative_categories.categories')) as $key) {
            $this->assertNotSame('', $this->builder->scale((string) $key), "Trade [{$key}] has no scale clause.");
        }
    }

    public function test_the_guardrail_folds_every_protected_feature_into_one_sentence(): void
    {
        $this->assertSame(
            'Keep the dial, the hands, the numerals, the date window, the bezel, the steel or leather strap and the logo unchanged.',
            $this->builder->guardrail('watches')
        );
    }

    public function test_an_absent_or_unknown_trade_says_nothing(): void
    {
        foreach ([null, '', '   ', 'spaceship', 'Saucer'] as $category) {
            $this->assertSame('', $this->builder->scale($category), "Category [{$category}] must contribute no scale.");
            $this->assertSame('', $this->builder->guardrail($category), "Category [{$category}] must contribute no guardrail.");
            $this->assertSame([], $this->builder->bannedWords($category), "Category [{$category}] must contribute no bans.");
        }
    }

    public function test_the_lookup_is_case_insensitive(): void
    {
        $this->assertSame($this->builder->scale('jewelry'), $this->builder->scale('Jewelry'));
        $this->assertSame($this->builder->guardrail('jewelry'), $this->builder->guardrail('  JEWELRY  '));
    }

    public function test_banned_words_are_the_other_trades_signature_nouns(): void
    {
        $this->assertSame(
            ['wristwatch', 'sneaker', 'handbag', 'perfume bottle', 'lipstick'],
            $this->builder->bannedWords('jewelry')
        );
        $this->assertSame([], $this->builder->bannedWords('general'), 'The fallback trade bans nothing.');
    }
}
