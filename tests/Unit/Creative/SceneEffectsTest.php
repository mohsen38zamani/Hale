<?php

namespace Tests\Unit\Creative;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use Tests\TestCase;

class SceneEffectsTest extends TestCase
{
    /**
     * The clause budget: the scene summary has to stay one readable sentence.
     */
    private const MAX_CLAUSE_LENGTH = 90;

    public function test_every_studio_control_explains_every_option_in_persian(): void
    {
        $effects = (array) config('creative.effects');

        $this->assertSame(
            array_keys((array) config('creative.impacts')),
            array_keys($effects),
            'Effects must be keyed by the studio field name exactly like impacts.'
        );

        foreach ($this->optionsByControl() as $control => $options) {
            $clauses = (array) ($effects[$control] ?? []);

            $this->assertSame(
                array_values($options),
                array_keys($clauses),
                "Control [{$control}] must explain every one of its options, in catalogue order."
            );

            foreach ($clauses as $key => $clause) {
                $this->assertNotSame('', trim($clause), "Effect [{$control}.{$key}] is empty.");
                $this->assertSame($clause, trim($clause), "Effect [{$control}.{$key}] carries padding spaces.");
                $this->assertLessThanOrEqual(
                    self::MAX_CLAUSE_LENGTH,
                    mb_strlen($clause),
                    "Effect [{$control}.{$key}] is too long to keep the scene summary readable."
                );
                $this->assertMatchesRegularExpression(
                    '/[\x{0600}-\x{06FF}]/u',
                    $clause,
                    "Effect [{$control}.{$key}] must be written in Persian for the studio."
                );
            }
        }
    }

    public function test_the_summary_template_covers_the_product_and_exactly_the_studio_controls(): void
    {
        preg_match_all('/\{(\w+)\}/u', (string) config('creative.summary_template'), $matches);
        $placeholders = $matches[1];

        $expected = array_merge(['product'], array_keys((array) config('creative.effects')));
        sort($expected);
        $actual = $placeholders;
        sort($actual);

        $this->assertSame(
            $expected,
            $actual,
            'The template needs one slot per studio control plus the product, and nothing else.'
        );
        $this->assertSame(
            array_values($placeholders),
            array_unique($placeholders),
            'No slot may be repeated, or one choice would be described twice.'
        );
    }

    public function test_the_template_renders_one_complete_sentence_from_the_first_option_of_every_control(): void
    {
        $effects = (array) config('creative.effects');
        $map = ['{product}' => 'عطر شما'];
        $clauses = [];

        foreach ($effects as $control => $options) {
            // The catalogue is written default-first, which is what the studio
            // preselects, so this is the sentence a fresh visitor reads.
            $key = array_key_first($options);
            $map['{'.$control.'}'] = $options[$key];
            $clauses[$control] = $options[$key];
        }

        $sentence = strtr((string) config('creative.summary_template'), $map);

        $this->assertStringNotContainsString('{', $sentence, 'Every slot must be filled.');
        $this->assertStringNotContainsString('}', $sentence, 'Every slot must be filled.');
        $this->assertStringStartsWith('عطر شما', $sentence);
        $this->assertStringEndsWith('.', $sentence);
        $this->assertStringContainsString('چیده می‌شود', $sentence);

        foreach ($clauses as $control => $clause) {
            $this->assertStringContainsString($clause, $sentence, "Control [{$control}] is missing from the sentence.");
        }
    }

    /**
     * Every option catalogue the studio renders, keyed by the field name the
     * radio inputs use.
     *
     * @return array<string, array<int, string>>
     */
    private function optionsByControl(): array
    {
        return [
            'goal' => array_map(fn ($case) => $case->value, CreativeGoal::cases()),
            'style' => array_map(fn ($case) => $case->value, CreativeStyle::cases()),
            'format' => array_map(fn ($case) => $case->value, CreativeFormat::cases()),
            'environment' => array_values((array) config('creative.environments')),
            'surface' => array_keys((array) config('creative.surfaces')),
            'lighting_setup' => array_keys((array) config('creative.lighting_setups')),
            'camera_angle' => array_keys((array) config('creative.camera_angles')),
            'props' => array_keys((array) config('creative.props')),
            'character_consistency' => array_keys((array) config('creative.character_consistencies')),
        ];
    }
}
