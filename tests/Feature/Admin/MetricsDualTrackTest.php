<?php

namespace Tests\Feature\Admin;

use App\Domains\Billing\Models\Payment;
use App\Domains\Credits\Models\CreditTransaction;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MetricsDualTrackTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_metrics_reports_split_dual_track_unit_economics(): void
    {
        config(['auth.admin_emails' => 'admin@hale.test']);
        $admin = User::factory()->create(['email' => 'admin@hale.test']);

        $user1 = User::factory()->create(['email' => 'user1@test.com']);
        $credits = app(CreditService::class);
        $credits->initialize($user1, 100);

        $product = $user1->products()->create(['name' => 'Watch', 'category' => 'watches']);
        $project = $user1->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'prompt' => 'P',
            'brief' => ['b' => 1],
        ]);

        // 1 completed generation (cost $0.05, spent 10 credits)
        $gen = Generation::create([
            'user_id' => $user1->id,
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'completed',
            'cost_usd' => 0.05,
            'prompt_hash' => hash('sha256', 'p1'),
            'credits_charged' => 10,
        ]);

        CreditTransaction::create([
            'credit_account_id' => $credits->account($user1)->id,
            'user_id' => $user1->id,
            'generation_id' => $gen->id,
            'type' => 'charge',
            'delivery_path' => 'in_app_generation',
            'amount' => -10,
            'internal_cost_usd' => 0.05,
            'balance_after' => 90,
            'idempotency_key' => 'idem:gen:1',
        ]);

        // 5 prompt compilations (spent 2 credits each = 10 credits)
        for ($i = 1; $i <= 5; $i++) {
            $credits->spendForPrompt($user1, 2, "idem:prompt:{$i}", [
                'product_id' => $product->id,
                'category' => 'watches',
                'target_ai' => 'midjourney',
            ]);
        }

        // Total spent: 10 credits (in-app) + 10 credits (prompt) = 20 credits.
        // Equal 50/50 split of consumption!

        // Payment: 1,000,000 Tomans
        Payment::create([
            'user_id' => $user1->id,
            'plan_key' => 'starter',
            'amount' => 1000000,
            'currency' => 'IRT',
            'status' => 'paid',
            'gateway' => 'zarinpal',
            'authority' => 'A12345678901234567890123456789012345',
            'idempotency_key' => 'idem_pay_dual',
            'paid_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/metrics');

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Overview
        $response->assertJsonPath('data.overview.total_prompts_compiled', 5)
            ->assertJsonPath('data.overview.in_app_credits_spent', 10)
            ->assertJsonPath('data.overview.prompt_only_credits_spent', 10);

        // Unit economics: 50% revenue to in-app (500,000) and 50% to prompt (500,000)
        $inAppEcon = $response->json('data.unit_economics.in_app_generation');
        $this->assertSame(500000, $inAppEcon['revenue_toman']);
        $this->assertEquals(0.05, $inAppEcon['cost_usd']);
        $this->assertSame(1, $inAppEcon['orders_count']);

        $promptEcon = $response->json('data.unit_economics.prompt_only');
        $this->assertSame(500000, $promptEcon['revenue_toman']);
        $this->assertSame(5, $promptEcon['prompts_count']);
        // 5 * 0.0006 = 0.0030 USD
        $this->assertEquals(0.003, $promptEcon['cost_usd']);
        // Margin on prompt track should be > 99% (> 85% target)
        $this->assertTrue($promptEcon['margin_percentage'] > 90);
        $this->assertSame('pass', $promptEcon['status']);

        // Blended margins
        $blended = $response->json('data.unit_economics.blended');
        $this->assertSame(1000000, $blended['revenue_toman']);
        $this->assertEquals(0.053, $blended['cost_usd']);
    }
}
