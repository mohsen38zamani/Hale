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
            ->assertSee('data-chip-character', false)
            ->assertSee('data-scene-controls-panel', false)
            ->assertSee('data-surfaces', false)
            ->assertSee('data-props', false)
            ->assertSee('data-camera-angles', false)
            ->assertSee('data-lighting-setups', false)
            ->assertSee('data-character-consistencies', false)
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
        $this->assertStringContainsString('Character consistency (locked):', $script, 'The prompt inspector must mirror the character consistency directive.');

        foreach (array_keys(config('creative.camera_angles')) as $key) {
            $this->assertStringContainsString(".studio-stage.angle-{$key}", $stylesheet, "Camera angle [{$key}] is missing a live preview style.");
        }
    }

    public function test_each_box_title_carries_exactly_one_impact_tag(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));
        $stylesheet = (string) file_get_contents(resource_path('css/app.css'));
        $view = (string) file_get_contents(resource_path('views/create.blade.php'));

        $this->assertStringNotContainsString(
            'impact-badge',
            $script,
            'The impact tag belongs on the box title, not on every chip.'
        );

        $this->assertSame(
            count(config('creative.impacts')),
            substr_count($view, 'data-impact-hint'),
            'Every impact-aware control needs exactly one caption explaining what it changes.'
        );

        foreach (array_keys(config('creative.impact_levels')) as $key) {
            $this->assertStringContainsString(".impact-badge.impact-{$key}", $stylesheet, "Impact level [{$key}] is missing a tag style.");
        }

        $html = (string) $this->get('/create')->assertOk()->baseResponse->getContent();

        foreach (config('creative.impacts') as $control => $level) {
            $this->assertStringContainsString(
                "impact-badge impact-{$level}\" data-impact-control=\"{$control}\"",
                $html,
                "Box [{$control}] must show one [{$level}] tag next to its title."
            );
        }

        $this->assertSame(
            count(config('creative.impacts')),
            substr_count($html, 'data-impact-control='),
            'Exactly one impact tag must be rendered per box.'
        );
    }

    public function test_the_studio_has_a_permanent_seasonal_theme_status_and_picker(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));
        $stylesheet = (string) file_get_contents(resource_path('css/app.css'));
        $html = (string) $this->get('/create')->assertOk()->baseResponse->getContent();

        // Status bar sits above the studio form: name, colour swatches and the
        // change/off buttons are always reachable without scrolling to the canvas.
        $status = strpos($html, 'data-season-status');
        $form = strpos($html, 'data-builder-form');
        $this->assertNotFalse($status, 'The seasonal status bar must render on the studio page.');
        $this->assertNotFalse($form, 'The studio form must render.');
        $this->assertLessThan($form, $status, 'The status bar must sit above the studio form.');

        foreach (['data-season-badge', 'data-season-swatches', 'data-season-status-text', 'data-season-change', 'data-season-toggle', 'data-season-picker', 'data-season-picker-grid', 'data-season-picker-close'] as $marker) {
            $this->assertStringContainsString($marker, $html, "Seasonal control [{$marker}] is missing from the studio page.");
        }

        // Every configured theme is listed with its palette and its effect.
        foreach (config('seasons.themes') as $theme) {
            $this->assertStringContainsString(
                "data-season-choice=\"{$theme['key']}\"",
                $html,
                "Theme [{$theme['key']}] must be listed in the picker."
            );
            $this->assertStringContainsString(
                "data-decor=\"{$theme['decor']}\"",
                $html,
                "Theme [{$theme['key']}] must preview its [{$theme['decor']}] effect."
            );
            $this->assertStringContainsString(
                'data-palette="'.implode(',', $theme['palette']).'"',
                $html,
                "Theme [{$theme['key']}] must preview its palette."
            );
        }

        // The state machine persists the choice and hands the pinned key to
        // the generation API so the prompt matches what the inspector showed.
        foreach (['hale-season-theme', 'season_theme: seasonThemeKey', 'writeSeasonChoice', 'applySeasonTheme'] as $snippet) {
            $this->assertStringContainsString($snippet, $script, "Studio script is missing [{$snippet}].");
        }

        $this->assertStringNotContainsString(
            'seasonOptedOut',
            $script,
            'The old read-once opt-out flag must be gone: the choice now lives in one shared reader.'
        );

        // The picker reuses the stage keyframes for its effect previews.
        $this->assertStringContainsString('.season-status', $stylesheet);
        $this->assertStringContainsString('.season-picker', $stylesheet);
        $this->assertStringContainsString('.season-tile.is-active', $stylesheet);
        foreach (['snowfall', 'vignette', 'sparkle', 'rain'] as $decor) {
            $this->assertStringContainsString(
                ".season-tile-preview[data-decor=\"{$decor}\"]",
                $stylesheet,
                "Effect preview [{$decor}] is missing from the picker tiles."
            );
        }
    }

    public function test_every_studio_choice_group_refreshes_the_prompt_inspector(): void
    {
        $view = (string) file_get_contents(resource_path('views/create.blade.php'));
        $script = (string) file_get_contents(resource_path('js/app.js'));

        // Every choice grid rendered in the studio must be covered by the one
        // shared change wiring: a forgotten group silently freezes the prompt
        // inspector, which is exactly how the character control shipped broken.
        preg_match_all('/class="choice-grid[^"]*"\s+(data-[a-z-]+)/', $view, $matches);
        $groups = array_values(array_unique($matches[1]));
        $this->assertNotEmpty($groups, 'The studio must render choice grids.');

        $anchor = strpos($script, '// One wiring pass over every choice grid');
        $this->assertNotFalse($anchor, 'The shared choice-group wiring must keep its anchor comment.');

        $marker = '.forEach((selector) => document.querySelector(selector)?.addEventListener(\'change\', updateCanvasState)';
        $position = strpos($script, $marker);
        $this->assertNotFalse($position, 'Every choice group must refresh the prompt inspector through the shared loop.');

        $wiring = substr($script, $anchor, $position + strlen($marker) - $anchor);
        preg_match_all('/\'\[(data-[a-z-]+)\]\'/', $wiring, $wired);
        $wiredGroups = $wired[1];

        $missing = array_values(array_diff($groups, $wiredGroups));
        $this->assertSame([], $missing, 'Every choice grid in the view must be wired to the prompt inspector.');
        $this->assertContains('data-environment', $wiredGroups, 'The environment select must stay wired too.');
        $this->assertSame(
            array_values(array_unique($wiredGroups)),
            $wiredGroups,
            'No selector may be listed twice in the wiring.'
        );

        // The selection must also reach the API, not only the inspector.
        $this->assertStringContainsString('character_consistency: values.character_consistency', $script);
    }
}
