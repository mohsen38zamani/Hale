<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_landing_page_renders_with_critical_elements(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('lang="fa"', false);
        $response->assertSee('rel="manifest"', false);
        $response->assertSee('rel="icon"', false);
        $response->assertSee('id="simulator"', false);
        $response->assertSee('id="compare"', false);
        $response->assertSee('flex-wrap: wrap', false, 'Hero CTAs must wrap on narrow phones.');
    }

    public function test_aurora_text_gradient_variable_is_defined_in_source_css(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString(
            '--aurora-text:',
            $css,
            'Undefined --aurora-text makes every .text-gradient heading render invisible.',
        );
    }

    public function test_mobile_navigation_hides_section_links_on_small_viewports(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 768px\).*?\.nav-links\s*\{\s*display:\s*none;/s',
            $css,
            'The five pill-nav links cannot fit a phone viewport.',
        );
    }

    public function test_fixed_navbar_anchor_targets_clear_the_pill_height(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.landing-page \[id\]\s*\{\s*scroll-margin-top:\s*96px;/',
            $css,
            'Without scroll-margin the section heading hides behind the fixed navbar.',
        );
    }

    public function test_manifest_uses_the_dark_theme_colors(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertSame('#06070B', $manifest['background_color']);
        $this->assertSame('#06070B', $manifest['theme_color']);
        $this->assertSame('fa', $manifest['lang']);
        $this->assertSame('rtl', $manifest['dir']);
    }
}
