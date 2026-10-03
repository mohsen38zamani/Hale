<?php

namespace Tests\Feature\Brand;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandKitTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_kit_endpoints_require_authentication(): void
    {
        $this->getJson('/api/brand-kit')->assertUnauthorized();
        $this->putJson('/api/brand-kit', [])->assertUnauthorized();
    }

    public function test_brand_kit_starts_empty_and_upserts(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->getJson('/api/brand-kit')->assertOk()->assertJsonPath('data', null);

        $this->putJson('/api/brand-kit', [
            'name' => 'برند آزمایشی',
            'primary_color' => '#0E0F12',
            'secondary_color' => '#FFFFFF',
            'accent_color' => '#D4AF37',
            'font_family' => 'Vazirmatn',
            'tone' => 'لوکس و مینیمال',
            'tagline' => 'زیبایی در جزئیات',
        ])->assertOk()
            ->assertJsonPath('data.name', 'برند آزمایشی')
            ->assertJsonPath('data.accent_color', '#D4AF37');

        // A second PUT updates the same row and leaves untouched fields alone.
        $this->putJson('/api/brand-kit', ['tone' => 'گرم و صمیمی'])
            ->assertOk()
            ->assertJsonPath('data.tone', 'گرم و صمیمی');

        $this->assertDatabaseCount('brand_kits', 1);
        $this->assertDatabaseHas('brand_kits', ['primary_color' => '#0E0F12', 'accent_color' => '#D4AF37']);
    }

    public function test_brand_kit_validates_colors_and_lengths(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/brand-kit', ['primary_color' => 'red'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['primary_color']);
        $this->putJson('/api/brand-kit', ['accent_color' => '#12345'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['accent_color']);
        $this->putJson('/api/brand-kit', ['tagline' => str_repeat('x', 161)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tagline']);
        $this->putJson('/api/brand-kit', ['tone' => str_repeat('x', 61)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tone']);

        $this->assertDatabaseCount('brand_kits', 0);
    }

    public function test_brand_kits_are_isolated_between_users(): void
    {
        Sanctum::actingAs($userA = User::factory()->create());
        $this->putJson('/api/brand-kit', ['primary_color' => '#111111'])->assertOk();

        Sanctum::actingAs($userB = User::factory()->create());
        $this->getJson('/api/brand-kit')->assertOk()->assertJsonPath('data', null);

        $this->assertDatabaseCount('brand_kits', 1);
        $this->assertNotSame($userA->id, $userB->id);
    }

    public function test_brand_identity_flows_into_generated_prompt(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);

        $this->putJson('/api/brand-kit', [
            'primary_color' => '#0E0F12',
            'accent_color' => '#D4AF37',
            'tone' => 'لوکس',
            'tagline' => 'زیبایی در جزئیات',
        ])->assertOk();

        $id = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'branding',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'environment' => 'luxury',
        ])->assertAccepted()->json('data.id');

        $generation = $this->getJson("/api/generations/{$id}")->assertOk();
        $prompt = (string) $generation->json('data.creative_project.prompt');
        $brief = $generation->json('data.creative_project.brief');

        $this->assertStringContainsString('Brand identity: palette primary #0E0F12, accent #D4AF37; tone لوکس.', $prompt);
        $this->assertStringContainsString('Keep the brand tagline "زیبایی در جزئیات" legible in the frame.', $prompt);
        $this->assertSame('لوکس', $brief['brand']['tone'] ?? null);
        $this->assertSame('#0E0F12', $brief['brand']['primary_color'] ?? null);
    }

    public function test_generated_prompt_stays_clean_without_a_brand_kit(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'کفش']);

        $id = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'professional',
            'format' => 'instagram_post',
            'environment' => 'studio',
        ])->assertAccepted()->json('data.id');

        $generation = $this->getJson("/api/generations/{$id}")->assertOk();
        $prompt = (string) $generation->json('data.creative_project.prompt');
        $brief = $generation->json('data.creative_project.brief');

        $this->assertStringNotContainsString('Brand identity', $prompt);
        $this->assertStringNotContainsString('brand tagline', $prompt);
        $this->assertArrayNotHasKey('brand', $brief);
    }

    public function test_dashboard_exposes_the_brand_kit_panel(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('data-brand-kit', false)
            ->assertSee('data-brand-save', false);
    }
}
