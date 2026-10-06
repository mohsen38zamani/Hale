<?php

namespace Tests\Feature\Templates;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenerationTemplateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'کمپین لوکس',
            'settings' => [
                'goal' => 'branding',
                'style' => 'luxury',
                'format' => 'instagram_post',
                'environment' => 'luxury',
                'surface' => 'marble',
                'props' => 'none',
                'camera_angle' => 'eye_level',
                'lighting_setup' => 'softbox',
                'custom_prompt' => 'با نور طلایی ملایم',
            ],
        ], $overrides);
    }

    public function test_template_endpoints_require_authentication(): void
    {
        $this->getJson('/api/templates')->assertUnauthorized();
        $this->postJson('/api/templates', $this->payload())->assertUnauthorized();
        $this->deleteJson('/api/templates/1')->assertUnauthorized();
    }

    public function test_user_can_create_read_update_and_delete_templates(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/templates', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'کمپین لوکس')
            ->assertJsonPath('data.settings.goal', 'branding')
            ->assertJsonPath('data.settings.surface', 'marble')
            ->json('data.id');

        $this->getJson('/api/templates')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $id);

        $this->getJson("/api/templates/{$id}")
            ->assertOk()
            ->assertJsonPath('data.settings.custom_prompt', 'با نور طلایی ملایم');

        $this->putJson("/api/templates/{$id}", ['name' => 'کمپین زمستان'])
            ->assertOk()
            ->assertJsonPath('data.name', 'کمپین زمستان');

        $this->deleteJson("/api/templates/{$id}")->assertOk();
        $this->assertDatabaseCount('generation_templates', 0);
        $this->getJson("/api/templates/{$id}")->assertNotFound();
    }

    public function test_templates_are_isolated_between_users(): void
    {
        Sanctum::actingAs($owner = User::factory()->create());
        $id = $this->postJson('/api/templates', $this->payload())->assertCreated()->json('data.id');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/templates')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson("/api/templates/{$id}")->assertNotFound();
        $this->putJson("/api/templates/{$id}", ['name' => 'سرقت'])->assertNotFound();
        $this->deleteJson("/api/templates/{$id}")->assertNotFound();

        $this->assertDatabaseHas('generation_templates', ['id' => $id, 'name' => 'کمپین لوکس']);
    }

    public function test_template_settings_validate_against_studio_options(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/templates', $this->payload(['settings' => ['goal' => 'unknown_goal']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.goal']);

        $this->postJson('/api/templates', $this->payload(['settings' => ['format' => 'billboard']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.format']);

        $this->postJson('/api/templates', $this->payload(['settings' => ['surface' => 'lava']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.surface']);

        $this->postJson('/api/templates', $this->payload(['settings' => ['custom_prompt' => str_repeat('x', 1001)]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.custom_prompt']);

        // Templates are product-agnostic: a product_id inside settings is rejected.
        $this->postJson('/api/templates', $this->payload(['settings' => ['product_id' => 5]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.product_id']);

        $this->postJson('/api/templates', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'settings']);

        $this->assertDatabaseCount('generation_templates', 0);
    }

    public function test_studio_page_exposes_the_template_controls(): void
    {
        $this->get('/create')
            ->assertOk()
            ->assertSee('data-template-list', false)
            ->assertSee('data-template-save', false);
    }

    public function test_template_supports_character_consistency(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/templates', $this->payload([
            'settings' => ['character_consistency' => 'locked'],
        ]))->assertCreated()
            ->assertJsonPath('data.settings.character_consistency', 'locked')
            ->json('data.id');

        $this->getJson("/api/templates/{$id}")
            ->assertOk()
            ->assertJsonPath('data.settings.character_consistency', 'locked');

        $this->postJson('/api/templates', $this->payload([
            'settings' => ['character_consistency' => 'invalid_state'],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['settings.character_consistency']);
    }
}
