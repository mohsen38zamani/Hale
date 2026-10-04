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
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme', null);

        config(['seasons.active' => 'no_such_theme']);
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme', null);
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

        // The autumn gap (09-22 -> 11-30) has no theme at all.
        Carbon::setTestNow('2026-10-15 10:00:00');
        $this->getJson('/api/creative/theme')->assertOk()->assertJsonPath('data.theme', null);
    }

    public function test_every_configured_theme_has_a_renderable_shape(): void
    {
        $themes = config('seasons.themes');
        $this->assertNotEmpty($themes);

        foreach ($themes as $theme) {
            $this->assertMatchesRegularExpression('/^\d{2}-\d{2}$/', $theme['start']);
            $this->assertMatchesRegularExpression('/^\d{2}-\d{2}$/', $theme['end']);
            $this->assertContains($theme['decor'], ['snowfall', 'vignette', 'sparkle']);
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
