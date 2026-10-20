<?php

namespace Tests\Feature\Credits;

use App\Domains\Admin\Models\SystemSetting;
use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DualTrackEstimationTest extends TestCase
{
    use RefreshDatabase;

    public function test_estimate_returns_costs_for_both_tracks(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Track 1 (image standard) + prompt cost for trade
        $response = $this->postJson('/api/credits/estimate', [
            'type' => 'image',
            'quality' => 'standard',
            'category' => 'watches',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cost', 10)
            ->assertJsonPath('data.prompt_cost', 2);
    }

    public function test_estimate_for_video_carries_video_generation_and_direction_prompt_costs(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Video: 20 base + 5s * 5 = 45 credits. Prompt: 5 credits.
        $response = $this->postJson('/api/credits/estimate', [
            'type' => 'video',
            'video_duration_seconds' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.cost', 45)
            ->assertJsonPath('data.prompt_cost', 5);
    }

    public function test_estimate_directly_for_prompt_only_track(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Simple text prompt
        $this->postJson('/api/credits/estimate', [
            'type' => 'prompt',
            'prompt_type' => 'simple',
        ])->assertOk()
            ->assertJsonPath('data.cost', 1);

        // Trade-specific image prompt (gold, watches, etc.)
        $this->postJson('/api/credits/estimate', [
            'type' => 'prompt',
            'prompt_type' => 'image',
            'category' => 'jewelry',
        ])->assertOk()
            ->assertJsonPath('data.cost', 2);

        // Video direction prompt
        $this->postJson('/api/credits/estimate', [
            'type' => 'prompt',
            'prompt_type' => 'video',
        ])->assertOk()
            ->assertJsonPath('data.cost', 5);
    }

    public function test_preview_endpoint_carries_estimated_credits_and_estimated_prompt_credits(): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create([
            'name' => 'عطر لوکس',
            'category' => 'perfumes',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'format' => CreativeFormat::InstagramPost->value,
            'category' => 'perfumes',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.estimated_credits', 10)
            ->assertJsonPath('data.estimated_prompt_credits', 2);
    }

    public function test_preview_accepts_undecided_studio_fields_sent_as_explicit_null(): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create([
            'name' => 'عطر لوکس',
            'category' => 'perfumes',
        ]);
        Sanctum::actingAs($user);

        // Browser parity: an untouched studio posts its empty fields as
        // explicit nulls, and null must fall back to autoBest - not 422 on
        // validation and not a TypeError inside CreativeGoal::from(null).
        $response = $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'goal' => null,
            'style' => null,
            'format' => null,
            'environment' => null,
            'category' => null,
            'fidelity_mode' => null,
            'surface' => null,
            'props' => null,
            'camera_angle' => null,
            'lighting_setup' => null,
            'character_consistency' => null,
            'campaign' => null,
            'season_theme' => null,
            'target_ai' => null,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.goal', 'introduction')
            ->assertJsonPath('data.estimated_credits', 10);
        $this->assertNotEmpty($response->json('data.prompt_preview'));
    }

    public function test_estimate_tells_the_two_tracks_apart_on_affordability(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        app(CreditService::class)->initialize($user, 5);

        // 5 credits: not enough for an image (10), enough for its prompt (2).
        // The dual-track cards read these two flags separately, so they must
        // not collapse into one "sufficient" verdict.
        $this->postJson('/api/credits/estimate', [
            'type' => 'image',
            'quality' => 'standard',
            'category' => 'watches',
        ])->assertOk()
            ->assertJsonPath('data.sufficient', false)
            ->assertJsonPath('data.sufficient_for_prompt', true);
    }

    public function test_customized_system_settings_instantly_apply_to_estimation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        SystemSetting::set('credit_cost_image_standard', 15, 'integer', 'credits');
        SystemSetting::set('credit_cost_prompt_image', 4, 'integer', 'credits');

        $response = $this->postJson('/api/credits/estimate', [
            'type' => 'image',
            'quality' => 'standard',
            'category' => 'shoes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.cost', 15)
            ->assertJsonPath('data.prompt_cost', 4);
    }
}
