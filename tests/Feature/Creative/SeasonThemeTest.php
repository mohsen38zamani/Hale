<?php

namespace Tests\Feature\Creative;

use App\Domains\Creative\Services\SeasonThemeService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SeasonThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        parent::tearDown();
        Carbon::setTestNow();
    }

    public function test_theme_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/creative/theme')->assertUnauthorized();
    }

    public function test_pinned_theme_is_returned_regardless_of_date(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['seasons.active' => 'yalda']);

        $this->getJson('/api/creative/theme')
            ->assertOk()
            ->assertJsonPath('data.theme.key', 'yalda')
            ->assertJsonPath('data.theme.name', 'یلدا')
            ->assertJsonPath('data.theme.emoji', '🍉')
            ->assertJsonPath('data.theme.decor', 'sparkle');
    }

    public function test_off_and_unknown_overrides_return_no_theme(): void
    {
        Sanctum::actingAs(User::factory()->create());

        config(['seasons.active' => 'off']);
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme', null)->assertJsonPath('data.enabled', false);

        config(['seasons.active' => 'no_such_theme']);
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme', null)->assertJsonPath('data.enabled', true);
    }

    public function test_theme_endpoint_lists_every_configured_theme(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['seasons.active' => 'auto']);

        $data = $this->getJson('/api/creative/theme')->assertOk()->json('data');

        $themes = $data['themes'];
        $this->assertCount(8, $themes, 'The picker must be able to list all eight seasonal themes.');
        $this->assertSame(array_column(config('seasons.themes'), 'key'), array_column($themes, 'key'));
        $this->assertTrue($data['enabled']);

        foreach ($themes as $theme) {
            foreach (['key', 'name', 'emoji', 'decor', 'campaign_label', 'prompt_pack'] as $field) {
                $this->assertNotEmpty($theme[$field], "Theme [{$theme['key']}] is missing [{$field}].");
            }

            $this->assertCount(3, $theme['palette'], "Theme [{$theme['key']}] must expose a three-colour palette.");
        }
    }

    public function test_the_deployment_kill_switch_keeps_the_catalogue_but_disables_theming(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['seasons.active' => 'off']);

        $data = $this->getJson('/api/creative/theme')->assertOk()->json('data');

        $this->assertNull($data['theme']);
        $this->assertFalse($data['enabled']);
        $this->assertCount(8, $data['themes'], 'The catalogue stays published so clients keep parsing it.');
    }

    public function test_auto_detection_uses_date_windows_with_precedence(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['seasons.active' => 'auto']);

        // Yalda overlaps the generic winter window and must win.
        Carbon::setTestNow('2026-12-25 10:00:00');
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme.key', 'yalda');

        // Winter wraps around New Year (12-01 -> 03-20).
        Carbon::setTestNow('2027-01-15 10:00:00');
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme.key', 'winter');

        Carbon::setTestNow('2026-03-24 10:00:00');
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme.key', 'nowruz');

        // Valentine sits inside the winter window and must win.
        Carbon::setTestNow('2027-02-14 10:00:00');
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme.key', 'valentine');

        // The former autumn gap now resolves to the rainy autumn theme and
        // hands over to Black Friday on 11-22.
        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme.key', 'autumn_rain');

        Carbon::setTestNow('2026-11-21 10:00:00');
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme.key', 'autumn_rain');

        Carbon::setTestNow('2026-11-25 10:00:00');
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme.key', 'black_friday');

        Carbon::setTestNow('2026-12-01 10:00:00');
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme.key', 'winter');
    }

    public function test_every_configured_theme_has_a_renderable_shape(): void
    {
        $themes = config('seasons.themes');
        $this->assertNotEmpty($themes);

        foreach ($themes as $theme) {
            $this->assertMatchesRegularExpression('/^\d{2}-\d{2}$/', $theme['start']);
            $this->assertMatchesRegularExpression('/^\d{2}-\d{2}$/', $theme['end']);
            $this->assertContains($theme['decor'], ['snowfall', 'vignette', 'sparkle', 'rain']);
            $this->assertCount(3, $theme['palette']);
            $this->assertNotEmpty($theme['name']);
            $this->assertNotEmpty($theme['emoji']);
        }

        // Key uniqueness keeps pinning unambiguous.
        $this->assertSame(count($themes), count(array_unique(array_column($themes, 'key'))));
    }

    public function test_studio_page_exposes_the_season_badge(): void
    {
        $this->get('/create')
            ->assertOk()
            ->assertSee('data-season-badge', false);
    }

    public function test_dashboard_has_a_permanent_seasonal_theme_section(): void
    {
        $html = (string) $this->get('/dashboard')->assertOk()->baseResponse->getContent();

        $this->assertStringContainsString('data-season-theme-section', $html, 'The dashboard must host the theme section.');
        $this->assertStringContainsString('data-season-status', $html, 'The status bar must render on the dashboard too.');
        $this->assertStringContainsString('data-season-picker-grid', $html, 'The picker must render on the dashboard too.');
        $this->assertStringContainsString('data-season-change', $html, 'The change button must stay reachable from the dashboard.');

        // Same catalogue as the studio, so both pages share one selection.
        foreach (config('seasons.themes') as $theme) {
            $this->assertStringContainsString("data-season-choice=\"{$theme['key']}\"", $html);
        }

        // The picker module must work on a page that has no studio canvas.
        $script = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('seasonStatus || seasonStage', $script);
        $this->assertStringContainsString('hale-season-theme', $script, 'The choice must persist across pages.');
    }

    public function test_service_respects_explicit_carbon_argument(): void
    {
        config(['seasons.active' => 'auto']);
        $service = app(SeasonThemeService::class);

        $theme = $service->active(Carbon::parse('2026-12-25 10:00:00'));
        $this->assertNotNull($theme);
        $this->assertSame('yalda', $theme['key']);

        $themeSpring = $service->active(Carbon::parse('2026-03-25 10:00:00'));
        $this->assertNotNull($themeSpring);
        $this->assertSame('nowruz', $themeSpring['key']);
    }
}
