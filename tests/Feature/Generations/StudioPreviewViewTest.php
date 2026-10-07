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
        $this->assertStringContainsString('Character consistency (${characterObj.key}):', $script, 'The prompt inspector must mirror the character consistency directive.');

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

    public function test_the_studio_renders_the_target_ai_selector_from_the_catalogue(): void
    {
        $html = (string) $this->get('/create')->assertOk()->baseResponse->getContent();
        $script = (string) file_get_contents(resource_path('js/app.js'));
        $stylesheet = (string) file_get_contents(resource_path('css/app.css'));

        // The catalogue, its categories and the preselected chip ride with the
        // page, so tabs, notes and gating are config-driven and the script
        // never has to know a single target key by name.
        $this->assertStringContainsString(
            'data-target-ais="'.e(json_encode(config('creative.target_ais'), JSON_UNESCAPED_UNICODE)).'"',
            $html,
            'The studio must receive the whole target catalogue with the page.'
        );
        $this->assertStringContainsString(
            'data-target-categories="'.e(json_encode(config('creative.target_ai_categories'), JSON_UNESCAPED_UNICODE)).'"',
            $html
        );
        $this->assertStringContainsString(
            'data-target-ai-default="'.config('creative.target_ai_default').'"',
            $html
        );

        foreach (['data-target-ai-field', 'data-target-ai-tabs', 'data-target-ai-grid', 'data-target-ai-note', 'data-target-ai-warning', 'data-chip-target-ai'] as $marker) {
            $this->assertStringContainsString($marker, $html, "Selector markup [{$marker}] is missing from the studio.");
        }

        $this->assertStringContainsString('builderForm.dataset.targetAis', $script);
        $this->assertStringContainsString('builderForm.dataset.targetCategories', $script);
        $this->assertStringContainsString('builderForm.dataset.targetAiDefault', $script);
        $this->assertStringContainsString(
            'Object.keys(targetCategories).map',
            $script,
            'The four tabs must be rendered from the configured categories.'
        );
        $this->assertStringContainsString(
            'input type="radio" name="target_ai"',
            $script,
            'A chip is a real form field: the choice must reach the payload, not only the canvas.'
        );

        // The refusal the API sends as a 422 has to be visible before the
        // click: a copy-only or format-mismatched target switches the generate
        // button off and says what to do instead.
        $this->assertStringContainsString("entry.mode !== 'generate'", $script, 'The copy-only gate must exist in the studio too.');
        $this->assertStringContainsString('generateBtn.disabled = notices.length > 0', $script);
        $this->assertStringContainsString('updateTargetAiState()', $script, 'Every state change must re-evaluate the gate.');
        $this->assertStringNotContainsString('value="generic"', $script, 'The preselected chip comes from config, never from the script.');

        // The choice travels with templates in both directions.
        $this->assertStringContainsString("'target_ai']", $script, 'The chip must be saved inside template settings.');
        $this->assertStringContainsString("'character_consistency', 'target_ai'].forEach", $script, 'Applying a template must restore the chip.');

        foreach (['.target-ai-tabs {', '.target-ai-tab.is-active', '.target-ai-choice.is-blocked', '.target-ai-warning {'] as $rule) {
            $this->assertStringContainsString($rule, $stylesheet, "Selector rule [{$rule}] is missing from the stylesheet.");
        }
    }

    public function test_the_studio_takes_the_character_default_from_the_server(): void
    {
        // The default cannot live only in config: the studio is served by
        // Blade, so the form carries it and the script reads it back.
        $this->get('/create')
            ->assertOk()
            ->assertSee('data-default-character="'.config('creative.character_consistency_default').'"', false);

        $script = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('builderForm.dataset.defaultCharacter', $script, 'The script must read the server-rendered default.');
        $this->assertStringContainsString('input[name="character_consistency"][value="${defaultCharacterKey}"]', $script, 'The preselected state must follow the server-rendered default.');
        $this->assertStringContainsString('character_consistency: values.character_consistency || defaultCharacterKey', $script, 'The payload fallback must follow the server-rendered default.');
        $this->assertStringNotContainsString('value="dynamic"', $script, 'The default must not be hard-coded in the script.');
    }

    public function test_the_prompt_inspector_mirrors_the_configured_text_suppression(): void
    {
        $html = (string) $this->get('/create')->assertOk()->baseResponse->getContent();
        $script = (string) file_get_contents(resource_path('js/app.js'));
        $engine = (string) file_get_contents(app_path('Domains/Creative/Services/CreativeEngine.php'));

        // Both directives and the wording keywords come from the same config key
        // the engine reads, rendered into the form, so the inspector cannot
        // disagree with the prompt the server is going to build.
        $this->assertStringContainsString('data-text-strict="'.config('creative.text_suppression.strict').'"', $html);
        $this->assertStringContainsString('data-text-permissive="'.config('creative.text_suppression.permissive').'"', $html);
        $this->assertStringContainsString('data-text-keywords=', $html, 'The wording keywords must reach the studio.');

        $this->assertStringContainsString('builderForm.dataset.textStrict', $script);
        $this->assertStringContainsString('builderForm.dataset.textPermissive', $script);
        $this->assertStringContainsString('builderForm.dataset.textKeywords', $script);
        $this->assertStringContainsString('no distracting watermarks, ${textClause}', $script, 'The mirror must end on the configured directive.');

        $this->assertStringNotContainsString('no unwanted text', $script, 'The weak tail must be gone from the mirror.');
        $this->assertStringNotContainsString('no unwanted text', $engine, 'The weak tail must be gone from the engine.');
    }

    public function test_the_studio_renders_the_scene_summary_from_the_config_template(): void
    {
        $html = (string) $this->get('/create')->assertOk()->baseResponse->getContent();

        $this->assertStringContainsString('data-scene-summary role="status"', $html, 'The summary bar must sit under the canvas.');
        $this->assertStringContainsString('data-scene-summary-text', $html, 'The summary needs its writable line.');
        $this->assertStringContainsString(
            'data-summary-template="'.config('creative.summary_template').'"',
            $html,
            'The grammar of the summary must come from config, not from the script.'
        );
        $this->assertStringContainsString(
            'data-scene-effects="'.e(json_encode(config('creative.effects'), JSON_UNESCAPED_UNICODE)).'"',
            $html,
            'The clauses must reach the studio with the page, not only through the authenticated API.'
        );

        $script = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('builderForm.dataset.sceneEffects', $script);
        $this->assertStringContainsString('builderForm.dataset.summaryTemplate', $script);
        $this->assertStringContainsString('[data-scene-summary-text]', $script);
        $this->assertStringContainsString('updateSceneSummary(', $script, 'The summary must be rewritten on every change.');
        $this->assertStringContainsString(
            'updateSceneSummary({',
            $script,
            'The summary must be fed by the same selections the inspector uses.'
        );

        $stylesheet = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.scene-summary {', $stylesheet);
        $this->assertStringContainsString('.scene-summary-text {', $stylesheet);
    }

    public function test_the_stage_simulates_horizon_light_props_and_environment(): void
    {
        $html = (string) $this->get('/create')->assertOk()->baseResponse->getContent();

        foreach (['data-canvas-horizon', 'data-canvas-lights', 'data-canvas-props'] as $layer) {
            $this->assertStringContainsString(
                $layer,
                $html,
                "Stage layer [{$layer}] is required so the configured scene can be simulated."
            );
        }

        $script = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('props-${props}', $script, 'The stage must follow the selected props.');
        $this->assertStringContainsString('env-${env}', $script, 'The atmosphere must follow the selected environment.');

        $stylesheet = (string) file_get_contents(resource_path('css/app.css'));

        foreach (array_keys((array) config('creative.lighting_setups')) as $key) {
            $this->assertStringContainsString(
                ".studio-stage.light-{$key} .stage-lights {",
                $stylesheet,
                "Lighting [{$key}] has no simulation inside the frame."
            );
        }

        // props-none is the honest default: the layer simply draws nothing.
        foreach (array_keys((array) config('creative.props')) as $key) {
            if ($key === 'none') {
                continue;
            }
            $this->assertStringContainsString(
                ".studio-stage.props-{$key} .stage-props::",
                $stylesheet,
                "Props [{$key}] has no drawn representation on the stage."
            );
        }

        foreach ((array) config('creative.environments') as $key) {
            $this->assertStringContainsString(
                ".stage-atmosphere.env-{$key}::before {",
                $stylesheet,
                "Environment [{$key}] has no tint layer."
            );
        }

        foreach (array_keys((array) config('creative.camera_angles')) as $key) {
            $this->assertStringContainsString(
                ".studio-stage.angle-{$key} .stage-horizon",
                $stylesheet,
                "Camera angle [{$key}] must place the horizon line itself, not only the product."
            );
        }
    }

    public function test_every_choice_card_carries_a_before_and_after_peek(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            "const stagePreviewControls = ['style', 'format', 'surface', 'props', 'camera_angle', 'lighting_setup']",
            $script,
            'Goal and character consistency only rewrite the prompt, so their cards must stay text-only.'
        );
        $this->assertStringContainsString('sceneEffects[name]', $script, 'Every card must quote its configured effect.');
        $this->assertStringContainsString('class="choice-peek"', $script);
        $this->assertStringContainsString('data-peek-now', $script);
        $this->assertStringContainsString('data-peek-next', $script);
        $this->assertStringContainsString('data-peek-control=', $script, 'The "with this option" miniature must know which key to swap.');
        $this->assertStringContainsString("addEventListener('pointerenter'", $script, 'The card must be filled when it is opened.');
        $this->assertStringContainsString("addEventListener('focusin'", $script, 'Keyboard users need the same explanation.');
        $this->assertStringContainsString(
            'studio-stage mini-stage',
            $script,
            'The miniature must be a real stage, otherwise the comparison would lie.'
        );
        $this->assertStringContainsString('--stage-product-image', $script, 'The comparison must show the real product photograph.');
        $this->assertStringContainsString(
            '.choice:hover .choice-peek',
            $script,
            'A radio clicked while hovering must refresh the card that is still open.'
        );

        $stylesheet = (string) file_get_contents(resource_path('css/app.css'));

        foreach (['.choice-peek {', '.choice-peek-text {', '.choice-peek-caption {', '.mini-stage {', '.mini-product {'] as $rule) {
            $this->assertStringContainsString($rule, $stylesheet, "Peek rule [{$rule}] is missing.");
        }

        $this->assertStringContainsString('var(--stage-product-image', $stylesheet);
        $this->assertStringContainsString('.choice:hover .choice-peek', $stylesheet);
        $this->assertStringContainsString('.choice:focus-within .choice-peek', $stylesheet);
        $this->assertStringContainsString(
            '.choice > span:not(.choice-peek)',
            $stylesheet,
            'The label rules must not paint over the card.'
        );
        $this->assertStringContainsString(
            '@media (hover: hover)',
            $stylesheet,
            'Touch devices must not be given a hover card.'
        );
    }
}
