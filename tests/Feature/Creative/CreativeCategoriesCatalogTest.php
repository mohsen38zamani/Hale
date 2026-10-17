<?php

namespace Tests\Feature\Creative;

use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Creative\Enums\CreativeWorkflow;
use App\Domains\Creative\Enums\FidelityMode;
use App\Domains\Creative\Enums\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The catalogue is the single source the compilers, the options endpoint
 * and the guided studio all read. These guards keep it honest: every key a
 * preset or a suggestion names must exist in the studio catalogues, and
 * every wording block must be the English a model can be pasted.
 */
class CreativeCategoriesCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_expose_the_ten_trades_in_catalogue_order(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $data = $this->getJson('/api/creative/options')->assertOk()->json('data');

        $categories = collect($data['categories']);

        $this->assertSame(
            array_column(ProductCategory::cases(), 'value'),
            $categories->pluck('key')->all(),
            'The options endpoint must publish the trades in catalogue order.'
        );

        foreach ($categories as $category) {
            foreach (['key', 'label', 'icon', 'scale_lock', 'protected_features', 'banned_words', 'presets'] as $field) {
                $this->assertArrayHasKey($field, $category, "Trade [{$category['key']}] does not publish [{$field}].");
            }

            $this->assertNotEmpty($category['label'], "Trade [{$category['key']}] has an empty label.");
            $this->assertNotEmpty($category['presets'], "Trade [{$category['key']}] offers no package.");
        }

        $this->assertSame(
            array_column(FidelityMode::cases(), 'value'),
            $data['fidelity_modes'],
            'Both fidelity pipelines must be published.'
        );
    }

    public function test_suggestions_and_presets_only_reference_defined_studio_options(): void
    {
        $surfaces = array_keys(config('creative.surfaces'));
        $props = array_keys(config('creative.props'));
        $cameras = array_keys(config('creative.camera_angles'));
        $lighting = array_keys(config('creative.lighting_setups'));

        foreach ($this->catalog() as $key => $category) {
            foreach (['surfaces' => $surfaces, 'props' => $props, 'camera_angles' => $cameras, 'lighting_setups' => $lighting] as $field => $defined) {
                foreach ($category[$field] as $suggested) {
                    $this->assertContains($suggested, $defined, "Trade [{$key}] suggests undefined [{$field}] entry [{$suggested}].");
                }
            }

            foreach ($category['presets'] as $preset) {
                $this->assertContains($preset['surface'], $surfaces, "Preset [{$key}/{$preset['key']}] names undefined surface [{$preset['surface']}].");
                $this->assertContains($preset['props'], $props, "Preset [{$key}/{$preset['key']}] names undefined prop [{$preset['props']}].");
                $this->assertContains($preset['camera_angle'], $cameras, "Preset [{$key}/{$preset['key']}] names undefined camera angle [{$preset['camera_angle']}].");
                $this->assertContains($preset['lighting_setup'], $lighting, "Preset [{$key}/{$preset['key']}] names undefined lighting [{$preset['lighting_setup']}].");
            }
        }
    }

    public function test_every_preset_answers_with_valid_enum_values(): void
    {
        $goals = array_column(CreativeGoal::cases(), 'value');
        $styles = array_column(CreativeStyle::cases(), 'value');
        $environments = config('creative.environments');
        $fidelityModes = array_column(FidelityMode::cases(), 'value');
        $workflows = array_column(CreativeWorkflow::cases(), 'value');

        foreach ($this->catalog() as $key => $category) {
            $this->assertCount(3, $category['presets'], "Trade [{$key}] must offer exactly three one-click packages.");

            $presetKeys = array_column($category['presets'], 'key');
            $this->assertSame(array_unique($presetKeys), $presetKeys, "Trade [{$key}] repeats a preset key.");

            foreach ($category['presets'] as $preset) {
                $this->assertContains($preset['goal'], $goals, "Preset [{$key}/{$preset['key']}] names an unknown goal.");
                $this->assertContains($preset['style'], $styles, "Preset [{$key}/{$preset['key']}] names an unknown style.");
                $this->assertContains($preset['environment'], $environments, "Preset [{$key}/{$preset['key']}] names an unknown environment.");
                $this->assertContains($preset['fidelity_mode'], $fidelityModes, "Preset [{$key}/{$preset['key']}] names an unknown fidelity mode.");
                $this->assertContains($preset['workflow'], $workflows, "Preset [{$key}/{$preset['key']}] names an unknown workflow.");
            }
        }
    }

    public function test_a_trade_never_bans_its_own_vocabulary(): void
    {
        foreach ($this->catalog() as $key => $category) {
            $wording = $category['scale_lock'].' '.implode(' ', $category['protected_features']);

            foreach ($category['banned_words'] as $word) {
                // Whole words only: fashion bans `ring` (the jewelry), and
                // its own wording may legitimately say "wearing".
                $this->assertDoesNotMatchRegularExpression(
                    '/\b'.preg_quote($word, '/').'\b/i',
                    $wording,
                    "Trade [{$key}] bans [{$word}] while its own wording uses it."
                );
            }
        }
    }

    public function test_only_the_fallback_trade_forbids_nothing(): void
    {
        foreach ($this->catalog() as $key => $category) {
            if ($key === 'general') {
                $this->assertEmpty($category['banned_words'], 'The fallback trade must not veto vocabulary.');

                continue;
            }

            $this->assertNotEmpty($category['banned_words'], "Trade [{$key}] has no banned vocabulary.");
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function catalog(): array
    {
        return config('creative_categories.categories');
    }
}
