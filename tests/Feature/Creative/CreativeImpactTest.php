<?php

namespace Tests\Feature\Creative;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreativeImpactTest extends TestCase
{
    use RefreshDatabase;

    /** Studio field names (radio `name`) that must always carry a level. */
    private const CONTROLS = [
        'goal',
        'style',
        'format',
        'environment',
        'surface',
        'lighting_setup',
        'camera_angle',
        'props',
        'character_consistency',
    ];

    public function test_creative_options_exposes_impact_levels_and_map(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/creative/options')->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'impacts',
                'impact_levels' => [
                    '*' => ['key', 'label', 'short', 'hint'],
                ],
            ],
        ]);

        $levels = collect($response->json('data.impact_levels'));
        $this->assertSame(
            ['high', 'medium', 'subtle'],
            $levels->pluck('key')->all(),
            'Exactly the three documented impact levels must be published.'
        );

        foreach ($levels as $level) {
            foreach (['label', 'short', 'hint'] as $field) {
                $this->assertNotEmpty($level[$field], "Impact level [{$level['key']}] is missing [{$field}].");
            }
        }

        $impacts = $response->json('data.impacts');
        $this->assertSame(self::CONTROLS, array_keys($impacts), 'Every studio control must publish an impact level.');
    }

    public function test_impact_weighting_follows_the_required_categories(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $impacts = $this->getJson('/api/creative/options')->assertOk()->json('data.impacts');

        $required = [
            'goal' => 'high',
            'style' => 'high',
            'format' => 'high',
            'camera_angle' => 'high',
            'character_consistency' => 'high',
            'environment' => 'medium',
            'surface' => 'medium',
            'lighting_setup' => 'medium',
            'props' => 'subtle',
        ];

        ksort($impacts);
        ksort($required);

        $this->assertSame($required, $impacts);
    }

    public function test_configured_impacts_only_reference_defined_levels(): void
    {
        $levels = array_keys(config('creative.impact_levels'));
        $impacts = config('creative.impacts');

        $this->assertSame(['high', 'medium', 'subtle'], $levels);

        foreach ($impacts as $control => $level) {
            $this->assertContains($level, $levels, "Control [{$control}] points at an undefined impact level [{$level}].");
            $this->assertContains($control, self::CONTROLS, "Unknown control [{$control}] carries an impact level.");
        }
    }
}
