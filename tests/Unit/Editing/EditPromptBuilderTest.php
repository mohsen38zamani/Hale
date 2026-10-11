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

    public function test_a_conversation_instruction_is_what_the_model_actually_reads(): void
    {
        $prompt = $this->builder->build('custom', ['message' => 'بطری را رو به چپ بچرخان']);

        $this->assertStringContainsString('بطری را رو به چپ بچرخان', $prompt);
        $this->assertStringContainsString('Keep everything you were not asked to change', $prompt);
        // The tool guard would veto the request it is attached to: it forbids
        // exactly the repositioning the user just asked for.
        $this->assertStringNotContainsString('unchanged in position, colour and proportions', $prompt);
    }

    public function test_an_empty_conversation_instruction_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->builder->build('custom', ['message' => '   ']);
    }

    public function test_each_variant_take_changes_a_different_axis_of_the_same_product(): void
    {
        $prompts = array_map(
            fn (int $index): string => $this->builder->build('variant', ['take_index' => $index]),
            range(0, count(EditPromptBuilder::TAKES) - 1),
        );

        $this->assertCount(3, array_unique($prompts));
        foreach ($prompts as $prompt) {
            $this->assertStringContainsString('changing only', $prompt);
            $this->assertStringContainsString('must stay exactly as it is', $prompt);
        }
    }

    public function test_an_out_of_range_take_falls_back_to_the_first_axis(): void
    {
        $this->assertSame(
            $this->builder->build('variant', ['take_index' => 0]),
            $this->builder->build('variant', ['take_index' => 99]),
        );
    }

    public function test_the_axes_exposed_to_the_api_carry_no_prompt_text(): void
    {
        foreach (EditPromptBuilder::takes() as $take) {
            $this->assertArrayHasKey('key', $take);
            $this->assertArrayHasKey('label', $take);
            $this->assertArrayNotHasKey('clause', $take);
        }
    }
}
