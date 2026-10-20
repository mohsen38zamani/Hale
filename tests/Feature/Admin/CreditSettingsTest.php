<?php

namespace Tests\Feature\Admin;

use App\Domains\Admin\Models\SystemSetting;
use App\Domains\Credits\Services\CreditEstimator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_credit_settings(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);
        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/settings/credits');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tracks.generation.image_standard', 10)
            ->assertJsonPath('data.tracks.generation.image_premium', 25)
            ->assertJsonPath('data.tracks.prompt.simple', 1)
            ->assertJsonPath('data.tracks.prompt.image', 2)
            ->assertJsonPath('data.tracks.prompt.video', 5);
    }

    public function test_admin_can_update_credit_rates(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);
        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/settings/credits', [
            'credit_cost_image_standard' => 12,
            'credit_cost_prompt_image' => 3,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tracks.generation.image_standard', 12)
            ->assertJsonPath('data.tracks.prompt.image', 3);

        $this->assertSame(12, SystemSetting::get('credit_cost_image_standard'));
        $this->assertSame(3, SystemSetting::get('credit_cost_prompt_image'));

        // Estimator immediately picks up the new setting
        $estimator = app(CreditEstimator::class);
        $this->assertSame(12, $estimator->estimate('image', null, 'standard'));
        $this->assertSame(3, $estimator->estimatePrompt('image', true));
    }

    public function test_non_admin_cannot_access_credit_settings(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);
        $user = User::factory()->create(['email' => 'user@regular.test']);
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/settings/credits')->assertForbidden();
        $this->postJson('/api/admin/settings/credits', ['credit_cost_image_standard' => 20])->assertForbidden();
    }

    public function test_validation_rejects_negative_or_extreme_rates(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);
        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/settings/credits', [
            'credit_cost_image_standard' => 0,
        ])->assertStatus(422);

        $this->postJson('/api/admin/settings/credits', [
            'credit_cost_prompt_simple' => 200,
        ])->assertStatus(422);
    }
}
