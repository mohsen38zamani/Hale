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

        // hero CTA row moved from inline styles into .hero-cta
        $response->assertSee('class="hero-cta"', false);
        $this->assertStringContainsString('flex-wrap: wrap', $this->landingCssSources());
    }

    public function test_landing_accessibility_wiring(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // skip link + main landmark
        $response->assertSee('class="skip-link"', false);
        $response->assertSee('<main id="main">', false);
        // labelled navigation
        $response->assertSee('<nav class="nav-links" aria-label=', false);
        // keyboard-operable before/after slider
        $response->assertSee('role="slider"', false);
        $response->assertSee('aria-valuenow="50"', false);
        $response->assertSee('tabindex="0"', false);

        $css = $this->landingCssSources();
        $this->assertStringContainsString('touch-action: none', $css, 'Slider drags must not scroll the page on touch devices.');
        $this->assertStringContainsString('.skip-link', $css);
        $this->assertStringContainsString('.faq-item', $css);
        $this->assertStringContainsString('.site-footer', $css);
    }

    public function test_landing_moved_inline_styles_into_classes(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $css = $this->landingCssSources();

        // Every scaffolding class the rebuild introduced must be styled.
        foreach (['section-head', 'section-title', 'section-lead', 'sim-status', 'sim-footer', 'ba-content', 'ba-pill', 'bento-tags', 'stats-grid', 'stat-value', 'pricing-cta', 'faq-list', 'cta-panel', 'site-footer-links'] as $class) {
            $this->assertStringContainsString($class, $html, "Missing .$class markup on the landing page.");
            $this->assertStringContainsString('.'.$class, $css, "Missing .$class rule in the landing stylesheets.");
        }
    }

    public function test_aurora_text_gradient_variable_is_defined_in_source_css(): void
    {
        $css = $this->landingCssSources();

        $this->assertStringContainsString(
            '--aurora-text:',
            $css,
            'Undefined --aurora-text makes every .text-gradient heading render invisible.',
        );
    }

    public function test_mobile_navigation_hides_section_links_on_small_viewports(): void
    {
        $css = $this->landingCssSources();

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 768px\).*?\.nav-links\s*\{\s*display:\s*none;/s',
            $css,
            'The five pill-nav links cannot fit a phone viewport.',
        );
    }

    public function test_fixed_navbar_anchor_targets_clear_the_pill_height(): void
    {
        $css = $this->landingCssSources();

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

    public function test_landing_exposes_seo_meta_tags(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="http://localhost"', false);
        $response->assertSee('<meta name="robots" content="index, follow">', false);
        $response->assertSee('<meta property="og:type" content="website">', false);
        $response->assertSee('<meta property="og:site_name" content="Hale">', false);
        $response->assertSee('<meta property="og:locale" content="fa_IR">', false);
        $response->assertSee('<meta property="og:title"', false);
        $response->assertSee('<meta property="og:description"', false);
        $response->assertSee('<meta name="twitter:card" content="summary">', false);
        $response->assertSee('<meta name="twitter:title"', false);
    }

    public function test_landing_exposes_valid_structured_data(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $this->assertCount(2, $matches[1], 'Expected SoftwareApplication + FAQPage JSON-LD blocks.');

        $software = json_decode($matches[1][0], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('SoftwareApplication', $software['@type']);
        $this->assertSame('Hale', $software['name']);
        $this->assertSame('DesignApplication', $software['applicationCategory']);
        $this->assertSame('http://localhost', $software['url']);

        $faq = json_decode($matches[1][1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('FAQPage', $faq['@type']);
        $this->assertCount(4, $faq['mainEntity']);

        // Every JSON-LD question must exist verbatim on the visible page.
        foreach ($faq['mainEntity'] as $question) {
            $this->assertSame('Question', $question['@type']);
            $this->assertStringContainsString($question['name'], $html);
            $this->assertNotEmpty($question['acceptedAnswer']['text']);
        }
    }

    public function test_structured_data_matches_the_rendered_faq_section(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // The JSON-LD answer text must match the <p> rendered inside #faq.
        preg_match('#<section class="faq-section" id="faq".*?</section>#s', $html, $section);
        $this->assertNotEmpty($section[0], 'FAQ section missing from landing page.');

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $faq = json_decode($matches[1][1], true, 512, JSON_THROW_ON_ERROR);

        foreach ($faq['mainEntity'] as $question) {
            $this->assertStringContainsString($question['acceptedAnswer']['text'], $section[0]);
        }
    }

    public function test_only_the_landing_page_loads_the_landing_stylesheet(): void
    {
        $this->get('/')->assertOk()->assertSee('/build/assets/landing-', false);

        foreach (['/dashboard', '/create', '/pricing', '/admin', '/terms'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertDontSee('/build/assets/landing-', false, "$uri must not ship landing-only CSS.");
        }
    }

    public function test_studio_simulator_shows_product_artwork_and_scenes(): void
    {
        $response = $this->get('/');
        $response->assertOk();

        // one inline SVG per sample product + the initial cinematic scene
        foreach (['perfume', 'shoe', 'watch'] as $art) {
            $response->assertSee('data-art="'.$art.'"', false, "Missing $art artwork in the simulator.");
        }
        $response->assertSee('class="sim-render-card scene-cinematic"', false);
        $response->assertSee('<svg viewBox="0 0 200 260"', false);
        $response->assertDontSee('sim-sample', false, 'The empty gradient placeholder box must be gone.');

        $css = $this->landingCssSources();
        foreach (['scene-cinematic', 'scene-minimal', 'scene-natural', 'scene-neon', '.sim-art svg'] as $token) {
            $this->assertStringContainsString($token, $css, "Missing simulator scene rule: $token");
        }
    }

    /**
     * The landing page loads app.css + landing.css; landing-only rules may
     * live in either file after the stylesheet split.
     */
    private function landingCssSources(): string
    {
        return file_get_contents(resource_path('css/app.css'))."\n"
            .file_get_contents(resource_path('css/landing.css'));
    }
}
