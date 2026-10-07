<?php

namespace Tests\Unit\Creative;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use Tests\TestCase;

class TextSuppressionGuardTest extends TestCase
{
    public function test_no_prompt_descriptor_can_ask_for_text_in_the_frame(): void
    {
        $banned = array_values(array_filter(array_map('strval', (array) config('creative.text_suppression.banned_prompt_words'))));
        $this->assertNotEmpty($banned, 'The banned word list must not be empty.');

        foreach ($this->promptDescriptors() as $source => $phrase) {
            $haystack = mb_strtolower($phrase);

            foreach ($banned as $word) {
                $this->assertStringNotContainsString(
                    mb_strtolower($word),
                    $haystack,
                    "Scene descriptor [{$source}] suggests a text-bearing composition: {$phrase}"
                );
            }
        }
    }

    public function test_the_suppression_catalogue_is_complete_and_contradiction_free(): void
    {
        $suppression = (array) config('creative.text_suppression');

        foreach (['strict', 'permissive', 'request_keywords', 'banned_prompt_words'] as $key) {
            $this->assertNotEmpty($suppression[$key] ?? null, "text_suppression.{$key} must stay defined.");
        }

        $strict = (string) $suppression['strict'];
        $permissive = (string) $suppression['permissive'];

        // Both branches forbid wording outright; only the permissive one may
        // carve out what the brief explicitly asked for, and only the strict
        // one may keep the product's own packaging artwork.
        $this->assertStringContainsString('no text', $strict);
        $this->assertStringContainsString('no typography', $strict);
        $this->assertStringContainsString('no fake labels', $strict);
        $this->assertStringContainsString('no artificial watermark', $strict);
        $this->assertStringContainsString('product packaging', $strict);
        $this->assertStringNotContainsString('except', $strict, 'The strict directive may not grant an exception.');

        $this->assertStringContainsString('no fake labels', $permissive);
        $this->assertStringContainsString('except the wording', $permissive);

        // Wording detection has to cover both languages the studio accepts.
        foreach (['متن', 'text'] as $keyword) {
            $this->assertContains($keyword, $suppression['request_keywords'], "Wording detection needs [{$keyword}].");
        }
    }

    /**
     * Every phrase that ends up inside a generated prompt.
     *
     * @return array<string, string>
     */
    private function promptDescriptors(): array
    {
        $descriptors = [];

        foreach (['surfaces', 'props', 'camera_angles', 'lighting_setups', 'character_consistencies'] as $group) {
            foreach ((array) config("creative.{$group}") as $key => $entry) {
                if (is_array($entry) && isset($entry['prompt'])) {
                    $descriptors["{$group}.{$key}"] = (string) $entry['prompt'];
                }
            }
        }

        foreach ((array) config('seasons.themes') as $key => $theme) {
            if (is_array($theme) && isset($theme['prompt_pack'])) {
                $descriptors["seasons.{$key}"] = (string) $theme['prompt_pack'];
            }
        }

        foreach ((array) config('creative.environments') as $environment) {
            $descriptors['environment.'.$environment] = (string) $environment;
        }

        foreach (CreativeGoal::cases() as $goal) {
            $descriptors['goal.'.$goal->value] = $goal->value;
        }

        foreach (CreativeStyle::cases() as $style) {
            $descriptors['style.'.$style->value] = $style->value;
        }

        foreach (CreativeFormat::cases() as $format) {
            $descriptors['format.'.$format->value] = $format->value;
        }

        return $descriptors;
    }
}
