<?php

namespace Tests\Feature\Admin;

use App\Domains\Billing\Models\Payment;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_metrics(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);

        $user = User::factory()->create(['email' => 'user@regular.test']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/metrics');

        $response->assertForbidden();
    }

    public function test_admin_receives_accurate_prd_kpi_calculations(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);

        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        $user1 = User::factory()->create(['email' => 'user1@test.com']);
        $user2 = User::factory()->create(['email' => 'user2@test.com']);
        $user3 = User::factory()->create(['email' => 'user3@test.com']);
        // Total users = 4 (admin + 3 users)

        $product1 = $user1->products()->create(['name' => 'Prod 1']);
        $project1 = $user1->creativeProjects()->create([
            'product_id' => $product1->id,
            'goal' => 'sales',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'prompt' => 'P1',
            'brief' => ['b' => 1],
        ]);

        $product2 = $user2->products()->create(['name' => 'Prod 2']);
        $project2 = $user2->creativeProjects()->create([
            'product_id' => $product2->id,
            'goal' => 'sales',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'prompt' => 'P2',
            'brief' => ['b' => 2],
        ]);

        // User 1 has 2 completed generations (both thumbs up)
        Generation::create([
            'user_id' => $user1->id,
            'creative_project_id' => $project1->id,
            'type' => 'image',
            'status' => 'completed',
            'feedback' => 'thumbs_up',
            'cost_usd' => 0.04,
            'prompt_hash' => hash('sha256', 'p1'),
        ]);
        Generation::create([
            'user_id' => $user1->id,
            'creative_project_id' => $project1->id,
            'type' => 'image',
            'status' => 'completed',
            'feedback' => 'thumbs_up',
            'cost_usd' => 0.04,
            'prompt_hash' => hash('sha256', 'p2'),
        ]);

        // User 2 has 1 completed generation (thumbs down)
        Generation::create([
            'user_id' => $user2->id,
            'creative_project_id' => $project2->id,
            'type' => 'image',
            'status' => 'completed',
            'feedback' => 'thumbs_down',
            'cost_usd' => 0.04,
            'prompt_hash' => hash('sha256', 'p3'),
        ]);

        // User 3 has 0 generations

        // User 1 has 1 paid payment of 500,000 Tomans
        Payment::create([
            'user_id' => $user1->id,
            'plan_key' => 'starter',
            'amount' => 500000,
            'currency' => 'IRT',
            'status' => 'paid',
            'gateway' => 'zarinpal',
            'authority' => 'A000000000000000000000000000000001',
            'idempotency_key' => 'idem_pay_1',
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/metrics');

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Overview
        $this->assertSame(4, $response->json('data.overview.total_users'));
        $this->assertSame(3, $response->json('data.overview.completed_generations'));

        // Activation (2 activated users out of 4 total users = 50.0%)
        $this->assertSame(2, $response->json('data.kpis.activation.activated_users'));
        $this->assertEquals(50.0, $response->json('data.kpis.activation.rate_percentage'));

        // Second Generation (1 user with >= 2 gens out of 2 activated users = 50.0%)
        $this->assertSame(1, $response->json('data.kpis.second_generation.second_gen_users'));
        $this->assertEquals(50.0, $response->json('data.kpis.second_generation.rate_percentage'));

        // Thumbs Up Rate (2 thumbs up out of 3 feedbacks = 66.67%)
        $this->assertSame(2, $response->json('data.kpis.thumbs_up_rate.thumbs_up'));
        $this->assertSame(1, $response->json('data.kpis.thumbs_up_rate.thumbs_down'));
        $this->assertEquals(66.67, $response->json('data.kpis.thumbs_up_rate.rate_percentage'));

        // Free to Paid (1 paid user out of 4 total users = 25.0%)
        $this->assertSame(1, $response->json('data.kpis.free_to_paid.paid_users'));
        $this->assertEquals(25.0, $response->json('data.kpis.free_to_paid.rate_percentage'));

        // Financials
        $this->assertSame(500000, $response->json('data.kpis.gross_margin.revenue_toman'));
        $this->assertEquals(0.12, $response->json('data.kpis.gross_margin.cost_usd'));
        $this->assertTrue($response->json('data.kpis.gross_margin.margin_percentage') > 0);
    }
}
