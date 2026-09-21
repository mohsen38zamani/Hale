<?php

namespace Tests\Feature\E2E;

use App\Domains\Billing\Models\Payment;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaywallCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_paywall_to_checkout_and_plan_upgrade_flow(): void
    {
        config([
            'payment.driver' => 'fake',
            'payment.webhook_secret' => 'test-secret',
        ]);

        // 1. User registers and has 0 balance
        $user = User::factory()->create(['plan_key' => 'free']);
        app(\App\Domains\Credits\Services\CreditService::class)->initialize($user, 0);
        $product = $user->products()->create(['name' => 'تیشرت']);
        Sanctum::actingAs($user);

        // 2. User tries to generate with 0 credits -> hits 402 Paywall
        $generateFail = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'format' => 'instagram_post',
            'style' => 'luxury',
        ])->assertStatus(402)
          ->assertJsonPath('error.code', 'INSUFFICIENT_CREDITS');

        // 3. User views available plans
        $plansRes = $this->getJson('/api/plans')->assertOk();
        $this->assertNotEmpty($plansRes->json('data'));

        // 4. User starts checkout for starter plan
        $checkoutRes = $this->postJson('/api/subscriptions/checkout', [
            'plan_key' => 'starter',
        ])->assertCreated();

        $authority = $checkoutRes->json('data.authority');
        $paymentId = $checkoutRes->json('data.payment_id');
        $this->assertNotEmpty($authority);

        // 5. Payment is settled via webhook
        $signature = hash_hmac('sha256', $authority.'|paid', 'test-secret');
        $this->postJson('/api/webhooks/payment', [
            'authority' => $authority,
            'status' => 'paid',
        ], ['X-Payment-Signature' => $signature])->assertOk();

        // 6. User profile reflects new plan and granted monthly credits
        $profileRes = $this->getJson('/api/user/profile')->assertOk();
        $this->assertSame('starter', $profileRes->json('data.plan_key'));
        $this->assertSame((int) config('plans.starter.monthly_credits'), $profileRes->json('data.credits_balance'));
        $this->assertNotNull($profileRes->json('data.subscription'));

        // 7. Payment history and invoice are available
        $historyRes = $this->getJson('/api/payments')->assertOk();
        $this->assertSame($paymentId, $historyRes->json('data.data.0.id'));
        $this->assertSame('paid', $historyRes->json('data.data.0.status'));

        $this->getJson("/api/payments/{$paymentId}/invoice")
            ->assertOk()
            ->assertJsonPath('data.number', "INV-{$paymentId}");

        // 8. User can now successfully generate
        Queue::fake([ProcessGeneration::class]);
        $generateSuccess = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'format' => 'instagram_post',
            'style' => 'luxury',
        ])->assertStatus(202);

        $this->assertSame('queued', $generateSuccess->json('data.status'));
        Queue::assertPushed(ProcessGeneration::class);
    }
}
