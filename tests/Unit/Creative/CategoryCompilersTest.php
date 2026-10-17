<?php

namespace Tests\Unit\Creative;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Prompts\PromptCompilerFactory;
use Tests\TestCase;

/**
 * A trade-bearing brief must reach every grammar with the two sentences it
 * cannot afford to lose - the real-world scale and the protected features -
 * and the other trades' nouns must reach the places that can ban them. A
 * brief without a trade must compile exactly as it did before the trade
 * studios existed: the first guard is what keeps every existing prompt
 * assertion in the suite honest.
 */
class CategoryCompilersTest extends TestCase
{
    private PromptCompilerFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new PromptCompilerFactory;
    }

    public function test_every_compiler_carries_the_scale_lock_and_the_guardrail(): void
    {
        foreach ($this->factory->keys() as $key) {
            $compiled = $this->factory->for($key)->compile($this->jewelryBrief(), CreativeFormat::InstagramPost);

            $this->assertStringContainsString('100mm lens', $compiled, "[{$key}] dropped the trade's scale lock.");
            $this->assertStringContainsString('prongs', $compiled, "[{$key}] dropped the protected features.");
            $this->assertStringContainsString('unchanged', $compiled, "[{$key}] did not tell the model to keep them.");
        }
    }

    public function test_the_video_compilers_carry_the_trade_too(): void
    {
        foreach (['veo', 'runway', 'generic'] as $key) {
            $compiled = $this->factory->for($key)->compile($this->jewelryBrief(), CreativeFormat::InstagramReel);

            $this->assertStringContainsString('100mm lens', $compiled, "[{$key}] dropped the scale lock on video.");
            $this->assertStringContainsString('prongs', $compiled, "[{$key}] dropped the guardrail on video.");
        }
    }

    public function test_a_brief_without_a_trade_never_mentions_scale_or_protection(): void
    {
        $brief = $this->jewelryBrief();
        unset($brief['category'], $brief['fidelity_mode']);

        foreach ($this->factory->keys() as $key) {
            $compiled = $this->factory->for($key)->compile($brief, CreativeFormat::InstagramPost);

            $this->assertStringNotContainsString('Physical scale', $compiled, "[{$key}] speaks of a scale the brief never chose.");
            $this->assertStringNotContainsString('100mm lens', $compiled, "[{$key}] leaked a trade's scale lock.");
            $this->assertStringNotContainsString('prongs', $compiled, "[{$key}] leaked a trade's protected features.");
            $this->assertStringNotContainsString('wristwatch', $compiled, "[{$key}] leaked a trade ban.");
        }
    }

    public function test_midjourney_bans_the_other_trades_in_its_own_grammar(): void
    {
        $compiled = $this->factory->for('midjourney')->compile($this->jewelryBrief(), CreativeFormat::InstagramPost);

        $this->assertStringEndsWith(
            '--no text, watermark, wristwatch, sneaker, handbag, perfume bottle, lipstick',
            $compiled,
            'Midjourney must receive the trade bans in its own `--no` grammar.'
        );
        $this->assertStringContainsString('--ar 1:1 --style raw --v 6.1', $compiled);
    }

    public function test_stable_diffusion_weighs_the_scale_and_bans_the_other_trades(): void
    {
        $compiled = $this->factory->for('stable_diffusion')->compile($this->jewelryBrief(), CreativeFormat::InstagramPost);

        [$positive, $negative] = explode("\n", $compiled);

        $this->assertStringContainsString('100mm lens', $positive, 'The scale lock belongs in the positive block.');
        $this->assertStringContainsString('(wristwatch:1.1)', $negative, 'The other trades belong in the negative block.');
        $this->assertStringContainsString('(perfume bottle:1.1)', $negative);
    }

    public function test_claude_wraps_the_fidelity_in_its_own_tags(): void
    {
        $compiled = $this->factory->for('claude')->compile($this->jewelryBrief(), CreativeFormat::InstagramPost);

        $this->assertStringContainsString('<product_fidelity>', $compiled);
        $this->assertStringContainsString('<physical_scale>Physical scale: ', $compiled);
        $this->assertStringContainsString('<protected_features>Keep the number and cut of the stones', $compiled);
        $this->assertStringContainsString('</product_fidelity>', $compiled);
    }

    public function test_gemini_edit_states_the_scale_it_must_keep(): void
    {
        $compiled = $this->factory->for('gemini_edit')->compile($this->jewelryBrief(), CreativeFormat::InstagramPost);

        $this->assertStringContainsString('Physical scale: macro photography, 100mm lens', $compiled);
        $this->assertStringContainsString('The product keeps this real-world size.', $compiled);
        $this->assertStringContainsString('Keep the number and cut of the stones', $compiled);
    }

    public function test_the_direction_compilers_label_the_trade_for_the_reader(): void
    {
        foreach (['chatgpt', 'grok', 'deepseek'] as $key) {
            $compiled = $this->factory->for($key)->compile($this->jewelryBrief(), CreativeFormat::InstagramPost);

            $this->assertStringContainsString('Physical scale: ', $compiled, "[{$key}] must label the scale clause.");
            $this->assertStringContainsString('Protected features: ', $compiled, "[{$key}] must label the guardrail.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function jewelryBrief(): array
    {
        return [
            'product' => 'انگشتر نقره',
            'description' => 'sterling silver ring with a small stone',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'luxury',
            'category' => 'jewelry',
            'fidelity_mode' => 'foreground_invariant_inpainting',
            'surface' => 'obsidian',
            'props' => 'crystals',
            'camera_angle' => 'macro',
            'lighting_setup' => 'rim',
            'custom_prompt' => null,
        ];
    }
}
