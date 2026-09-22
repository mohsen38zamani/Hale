<?php

namespace Tests\Feature\Billing;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ZarinpalSandboxIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payment.driver' => 'zarinpal',
            'payment.zarinpal.merchant_id' => '00000000-0000-0000-0000-000000000000',
            'payment.zarinpal.sandbox' => true,
            'payment.zarinpal.base_url' => 'https://sandbox.zarinpal.com',
            'payment.zarinpal.start_pay_url' => 'https://sandbox.zarinpal.com/pg/StartPay',
            'payment.zarinpal.callback_url' => 'http://localhost/api/payments/zarinpal/callback',
        ]);
    }

    public function test_complete_zarinpal_sandbox_checkout_and_callback_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'phone' => '+989121234567',
            'plan_key' => 'free',
        ]);
        app(CreditService::class)->initialize($user, 30);

        $authority = 'A0000000000000000000000000000SANDBOX1';

        Http::fake([
            'https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => $authority],
                'errors' => [],
            ], 200),
            'https://sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 100, 'ref_id' => 987654321],
                'errors' => [],
            ], 200),
        ]);

        // 1. Checkout via API
        Sanctum::actingAs($user);
        $checkoutResponse = $this->postJson('/api/subscriptions/checkout', [
            'plan_key' => 'starter',
        ]);

        $checkoutResponse->assertStatus(201)
            ->assertJsonPath('data.authority', $authority)
            ->assertJsonPath('data.redirect_url', "https://sandbox.zarinpal.com/pg/StartPay/{$authority}");

        $payment = Payment::query()->where('authority', $authority)->first();
        $this->assertNotNull($payment);
        $this->assertSame('pending', $payment->status);

        // 2. User redirected back via browser to callback
        $callbackResponse = $this->get("/api/payments/zarinpal/callback?Authority={$authority}&Status=OK");
        $callbackResponse->assertRedirect('/pricing?payment=paid');

        // 3. Assert payment, subscription, and credits state
        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('987654321', $payment->reference);
        $this->assertNotNull($payment->paid_at);

        $this->assertSame('starter', $user->fresh()->plan_key);
        $this->assertSame(230, app(CreditService::class)->account($user)->fresh()->balance); // 30 + 200
        $this->assertTrue(Subscription::query()->where('user_id', $user->id)->where('status', 'active')->exists());
    }

    public function test_zarinpal_sandbox_failed_callback_redirects_to_failed_pricing(): void
    {
        $user = User::factory()->create(['plan_key' => 'free']);
        $authority = 'A0000000000000000000000000000FAILED';

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'amount' => 4_990_000,
            'gateway' => 'zarinpal',
            'status' => 'pending',
            'authority' => $authority,
            'idempotency_key' => 'checkout:'.$user->id.':failed-1',
        ]);

        $response = $this->get("/api/payments/zarinpal/callback?Authority={$authority}&Status=NOK");
        $response->assertRedirect('/pricing?payment=failed');

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame('free', $user->fresh()->plan_key);
    }
}
