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
}
