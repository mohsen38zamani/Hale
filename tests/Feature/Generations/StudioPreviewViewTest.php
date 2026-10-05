<?php

namespace Tests\Feature\Generations;

use Tests\TestCase;

class StudioPreviewViewTest extends TestCase
{
    public function test_create_studio_page_renders_live_canvas_and_prompt_inspector(): void
    {
        $response = $this->get('/create');

        $response->assertOk()
            ->assertSee('استودیوی ساخت محتوا | حله')
            ->assertSee('data-builder-form', false)
            ->assertSee('data-custom-prompt', false)
            ->assertSee('data-custom-prompt-counter', false)
            ->assertSee('data-canvas-panel', false)
            ->assertSee('data-canvas-stage', false)
            ->assertSee('data-canvas-atmosphere', false)
            ->assertSee('data-canvas-style-badge', false)
            ->assertSee('data-canvas-format-chip', false)
            ->assertSee('data-canvas-ground', false)
            ->assertSee('data-canvas-product', false)
            ->assertSee('data-canvas-product-img', false)
            ->assertSee('data-canvas-placeholder', false)
            ->assertSee('data-prompt-inspector', false)
            ->assertSee('data-copy-prompt', false)
            ->assertSee('data-inspector-chips', false)
            ->assertSee('data-chip-product', false)
            ->assertSee('data-chip-goal', false)
            ->assertSee('data-chip-style', false)
            ->assertSee('data-chip-env', false)
            ->assertSee('data-chip-format', false)
            ->assertSee('data-chip-surface', false)
            ->assertSee('data-chip-props', false)
            ->assertSee('data-chip-camera', false)
            ->assertSee('data-chip-lighting', false)
            ->assertSee('data-scene-controls-panel', false)
            ->assertSee('data-surfaces', false)
            ->assertSee('data-props', false)
            ->assertSee('data-camera-angles', false)
            ->assertSee('data-lighting-setups', false)
            ->assertSee('data-inspector-code', false);
    }

    public function test_create_studio_page_has_proper_rtl_and_a11y_attributes(): void
    {
        $response = $this->get('/create');

        $response->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('lang="fa"', false)
            ->assertSee('role="alert"', false)
            ->assertSee('aria-live="polite"', false);
    }

    public function test_every_camera_angle_has_a_live_stage_preview_and_inspector_mirror(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));
        $stylesheet = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('angle-${cameraAngle}', $script, 'The studio must tag the live stage with the selected camera angle.');
        $this->assertStringContainsString('Camera angle (locked):', $script, 'The prompt inspector must mirror the front-loaded camera directive.');

        foreach (array_keys(config('creative.camera_angles')) as $key) {
            $this->assertStringContainsString(".studio-stage.angle-{$key}", $stylesheet, "Camera angle [{$key}] is missing a live preview style.");
        }
    }

    public function test_every_impact_level_reaches_the_chips_and_section_captions(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));
        $stylesheet = (string) file_get_contents(resource_path('css/app.css'));
        $view = (string) file_get_contents(resource_path('views/create.blade.php'));

        $this->assertStringContainsString('options.data.impacts', $script, 'The studio must read the impact map from /api/creative/options.');
        $this->assertStringContainsString('impact-badge', $script, 'Every chip must carry the impact tag of its control.');

        $this->assertGreaterThanOrEqual(
            count(config('creative.impacts')),
            substr_count($view, 'data-impact-hint'),
            'Every impact-aware control needs a caption explaining what it changes in the output.'
        );

        foreach (array_keys(config('creative.impact_levels')) as $key) {
            $this->assertStringContainsString(".impact-badge.impact-{$key}", $stylesheet, "Impact level [{$key}] is missing a chip style.");
        }

        // The environment select cannot be tagged by JS, so the server must
        // stamp its level directly from config while rendering the page.
        $this->get('/create')
            ->assertOk()
            ->assertSee('impact-badge impact-medium', false)
            ->assertSee('data-impact-hint', false);
    }
}
