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
}
