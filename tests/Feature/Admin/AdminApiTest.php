<?php

namespace Tests\Feature\Admin;

use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_admin(): void
    {
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(401);
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);

        $user = User::factory()->create(['email' => 'regular@user.test']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_admin_can_list_users_and_search(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);

        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        $user1 = User::factory()->create(['name' => 'Sara Boutique', 'email' => 'sara@shop.test']);
        $user2 = User::factory()->create(['name' => 'Reza Clothes', 'email' => 'reza@shop.test']);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/users');
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(3, $response->json('data.data'));

        // Search test
        $searchResponse = $this->getJson('/api/admin/users?search=Sara');
        $searchResponse->assertOk();
        $this->assertCount(1, $searchResponse->json('data.data'));
        $this->assertSame('sara@shop.test', $searchResponse->json('data.data.0.email'));
    }

    public function test_admin_can_view_user_detail(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);

        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        $targetUser = User::factory()->create(['name' => 'Target User', 'email' => 'target@shop.test']);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/admin/users/{$targetUser->id}");
        $response->assertOk();
        $response->assertJsonPath('data.email', 'target@shop.test');
        $response->assertJsonPath('data.credits_balance', config('credits.initial_balance'));
    }

    public function test_admin_can_list_generations(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);

        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        $user = User::factory()->create(['email' => 'client@shop.test']);
        $product = $user->products()->create(['name' => 'Perfume', 'status' => 'active']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'introduction',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'brief' => ['summary' => 'perfume brief'],
            'prompt' => 'perfume prompt',
        ]);

        $generation = Generation::query()->create([
            'user_id' => $user->id,
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'perfume prompt'),
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/generations');
        $response->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame($generation->id, $response->json('data.data.0.id'));
    }

    public function test_admin_can_refund_credits_with_audit_trail(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);

        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        $user = User::factory()->create(['email' => 'client@shop.test']);

        $credits = app(CreditService::class);
        $initialBalance = $credits->account($user)->balance;

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/users/{$user->id}/refund", [
            'amount' => 50,
            'reason' => 'Customer compensation for system delay',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.amount_refunded', 50);
        $response->assertJsonPath('data.new_balance', $initialBalance + 50);

        // Verify transaction in DB
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $user->id,
            'type' => 'refund',
            'amount' => 50,
        ]);
    }
}
