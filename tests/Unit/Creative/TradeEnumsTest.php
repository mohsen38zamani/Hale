<?php

namespace Tests\Unit\Creative;

use App\Domains\Creative\Enums\CreativeWorkflow;
use App\Domains\Creative\Enums\FidelityMode;
use App\Domains\Creative\Enums\ReferenceMode;
use Tests\TestCase;

class TradeEnumsTest extends TestCase
{
    public function test_the_two_fidelity_pipelines_of_the_specification(): void
    {
        $this->assertSame(
            ['catalog_white_composite', 'foreground_invariant_inpainting'],
            array_column(FidelityMode::cases(), 'value')
        );

        $this->assertTrue(FidelityMode::CatalogWhiteComposite->isComposite());
        $this->assertFalse(FidelityMode::ForegroundInvariantInpainting->isComposite());
    }

    public function test_the_six_formal_workflows(): void
    {
        $this->assertSame(
            ['quick_shot', 'catalog', 'lifestyle', 'seasonal', 'creative_campaign', 'custom'],
            array_column(CreativeWorkflow::cases(), 'value')
        );
    }

    public function test_the_two_reference_modes(): void
    {
        $this->assertSame(
            ['style_inspo', 'scene_inspo'],
            array_column(ReferenceMode::cases(), 'value')
        );
    }
}
