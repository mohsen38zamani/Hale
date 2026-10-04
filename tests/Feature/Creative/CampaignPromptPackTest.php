<?php

namespace Tests\Feature\Creative;

use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignPromptPackTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Decode the persisted brief whether the column casts to array or not.
     *
     * @return array<string, mixed>
     */
    private function briefOf(mixed $brief): array
    {
        if (is_string($brief)) {
            $brief = json_decode($brief, true);
        }

        return is_array($brief) ? $brief : [];
    }

    public function test_theme_endpoint_exposes_campaign_pack_metadata(): void
    {
        Sanctum::actingAs(User::factory()->create());
        config(['seasons.active' => 'black_friday']);

        $response = $this->getJson('/api/creative/theme')->assertOk();
        $response
            ->assertJsonPath('data.theme.key', 'black_friday')
            ->assertJsonPath('data.theme.campaign_label', 'کمپین جمعه سیاه');
        $this->assertNotEmpty($response->json('data.theme.prompt_pack'));
    }

    public function test_preview_appends_the_campaign_pack_when_requested(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        config(['seasons.active' => 'black_friday']);
        $product = $user->products()->create(['name' => 'عطر']);

        $data = $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'campaign' => true,
        ])->assertOk()->json('data');

        $this->assertStringContainsString('Campaign mood:', $data['prompt_preview']);
        $this->assertStringContainsString('neon-gold', $data['prompt_preview']);
        $this->assertSame('black_friday', $data['brief']['campaign']['key'] ?? null);
        $this->assertSame('کمپین جمعه سیاه', $data['brief']['campaign']['label'] ?? null);
    }

    public function test_preview_without_the_flag_has_no_campaign_clause(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        config(['seasons.active' => 'black_friday']);
        $product = $user->products()->create(['name' => 'عطر']);

        $data = $this->postJson('/api/creative/preview', ['product_id' => $product->id])
            ->assertOk()
            ->json('data');

        $this->assertStringNotContainsString('Campaign mood:', $data['prompt_preview']);
        $this->assertArrayNotHasKey('campaign', $data['brief']);
    }

    public function test_campaign_is_ignored_when_theming_is_off(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        config(['seasons.active' => 'off']);
        $product = $user->products()->create(['name' => 'عطر']);

        $data = $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'campaign' => true,
        ])->assertOk()->json('data');

        $this->assertStringNotContainsString('Campaign mood:', $data['prompt_preview']);
        $this->assertArrayNotHasKey('campaign', $data['brief']);
    }

    public function test_campaign_flag_must_be_boolean(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $user->products()->create(['name' => 'عطر']);

        $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'campaign' => 'yes',
        ])->assertUnprocessable()->assertJsonValidationErrors(['campaign']);
    }

    public function test_store_keeps_campaign_inside_the_brief_only(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        config(['seasons.active' => 'black_friday']);
        $product = $user->products()->create(['name' => 'عطر']);

        $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'environment' => 'studio',
            'campaign' => true,
        ])->assertStatus(202);

        $generation = Generation::query()->latest('id')->firstOrFail();
        $project = $generation->creativeProject;

        // The flag must not reach the insert; only the brief carries it.
        $this->assertArrayNotHasKey('campaign', $project->getAttributes());

        $brief = $this->briefOf($project->brief);
        $this->assertSame('black_friday', $brief['campaign']['key'] ?? null);
        $this->assertStringContainsString('Campaign mood:', (string) $project->prompt);
    }

    public function test_studio_page_exposes_the_campaign_badge(): void
    {
        $this->get('/create')
            ->assertOk()
            ->assertSee('data-campaign-badge', false);
    }
}
