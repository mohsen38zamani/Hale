<?php

namespace Tests\Feature\Creative;

use Tests\TestCase;

class StudioDualTrackUiTest extends TestCase
{
    private const DUAL_TRACK_MARKERS = [
        'data-dual-track-selector',
        'data-track-card="generate"',
        'data-track-card="prompt"',
        'data-track-gen-credits',
        'data-track-prompt-credits',
        'data-dual-track-actions',
        'data-generate',
        'data-buy-prompt',
        'data-btn-prompt-cost',
        'data-credit-estimate',
    ];

    public function test_studio_renders_dual_track_cards_and_action_controls(): void
    {
        $response = $this->get('/create');

        $response->assertOk();
        $content = (string) $response->baseResponse->getContent();

        foreach (self::DUAL_TRACK_MARKERS as $marker) {
            $this->assertStringContainsString(
                $marker,
                $content,
                "Studio page at /create must render dual-track marker: {$marker}"
            );
        }

        $this->assertStringContainsString('تولید با AI حله', $content);
        $this->assertStringContainsString('فقط پرامپت مهندسی‌شده', $content);
    }

    public function test_pricing_page_renders_dual_track_structure_and_credit_packs(): void
    {
        $response = $this->get('/pricing');

        $response->assertOk()
            ->assertSee('data-pricing-grid', false)
            ->assertSee('data-packs-grid', false)
            ->assertSee('بسته‌های اعتبار', false)
            ->assertSee('pack-dual-track', false);
    }

    public function test_client_script_wires_dual_track_endpoints_and_idempotency(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('/api/creative/compile-prompt', $script);
        $this->assertStringContainsString('/api/credits/estimate', $script);
        $this->assertStringContainsString('data-buy-prompt', $script);
        $this->assertStringContainsString('data-track-card', $script);
        $this->assertStringContainsString('prompt_cost', $script);
        $this->assertStringContainsString('Idempotency-Key', $script);
    }
}
