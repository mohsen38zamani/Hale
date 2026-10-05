<?php

namespace Tests\Unit\Editing;

use App\Domains\Editing\Services\EditPromptBuilder;
use InvalidArgumentException;
use Tests\TestCase;

class EditPromptBuilderTest extends TestCase
{
    private EditPromptBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new EditPromptBuilder;
    }

    public function test_remove_bg_defaults_to_transparent_and_supports_white(): void
    {
        $transparent = $this->builder->build('remove_bg');
        $this->assertStringContainsString('transparent background', $transparent);
        $this->assertStringNotContainsString('#FFFFFF', $transparent);

        $white = $this->builder->build('remove_bg', ['background' => 'white']);
        $this->assertStringContainsString('#FFFFFF', $white);
        $this->assertStringContainsString('studio backdrop', $white);
    }

    public function test_upscale_targets_name_the_exact_resolution(): void
    {
        $this->assertStringContainsString('HD (1024 px', $this->builder->build('upscale'));
        $this->assertStringContainsString('2K (2048 px', $this->builder->build('upscale', ['target' => '2k']));
        $this->assertStringContainsString('4K (4096 px', $this->builder->build('upscale', ['target' => '4k']));
    }

    public function test_expand_carries_the_requested_aspect_ratio(): void
    {
        $prompt = $this->builder->build('expand', ['aspect_ratio' => '16:9']);
        $this->assertStringContainsString('16x9', $prompt);
        $this->assertStringContainsString('Do not crop, move or resize', $prompt);

        // Default ratio when none was passed.
        $this->assertStringContainsString('4x5', $this->builder->build('expand'));
    }

    public function test_shadow_distinguishes_grounding_shadow_from_reflection(): void
    {
        $shadow = $this->builder->build('shadow');
        $this->assertStringContainsString('grounding shadow', $shadow);
        $this->assertStringNotContainsString('reflection of the product', $shadow);

        $reflection = $this->builder->build('shadow', ['effect' => 'reflection']);
        $this->assertStringContainsString('reflection of the product', $reflection);
    }

    public function test_every_prompt_carries_the_subject_guardrail(): void
    {
        $guard = 'unchanged in position, colour and proportions';
        foreach (['remove_bg', 'upscale', 'expand', 'shadow'] as $operation) {
            $this->assertStringContainsString($guard, $this->builder->build($operation));
        }
    }

    public function test_unknown_operation_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->builder->build('magic');
    }
}
