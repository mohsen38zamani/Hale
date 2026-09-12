<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.webhook_secret' => 'test-secret']);
        config(['payment.driver' => 'fake']);
    }

    public function test_user_can_start_checkout_for_a_paid_plan(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/subscriptions/checkout', ['plan_key' => 'starter'])
            ->assertCreated()
            ->assertJsonPath('data.payment_id', 1)
            ->assertJsonPath('data.redirect_url', fn (string $url) => str_contains($url, 'fake-checkout'));

        $this->assertDatabaseHas('payments', [
            'id' => $response->json('data.payment_id'),
            'plan_key' => 'starter',
            'status' => 'pending',
            'amount' => 4_990_000,
        ]);
    }

    public function test_paid_webhook_activates_subscription_and_credits(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $authority = $this->postJson('/api/subscriptions/checkout', ['plan_key' => 'starter'])->json('data.authority');
        $signature = hash_hmac('sha256', $authority.'|paid', 'test-secret');

        $this->postJson('/api/webhooks/payment', ['authority' => $authority, 'status' => 'paid'], ['X-Payment-Signature' => $signature])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_key' => 'starter']);
        $this->assertDatabaseHas('subscriptions', ['user_id' => $user->id, 'plan_key' => 'starter', 'status' => 'active']);
        $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id, 'type' => 'purchase', 'amount' => 200]);
    }

    public function test_duplicate_paid_webhook_does_not_grant_credits_twice(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $authority = $this->postJson('/api/subscriptions/checkout', ['plan_key' => 'creator'])->json('data.authority');
        $headers = ['X-Payment-Signature' => hash_hmac('sha256', $authority.'|paid', 'test-secret')];

        $this->postJson('/api/webhooks/payment', compact('authority') + ['status' => 'paid'], $headers)->assertOk();
        $this->postJson('/api/webhooks/payment', compact('authority') + ['status' => 'paid'], $headers)->assertOk();

        $this->assertDatabaseCount('credit_transactions', 1);
        $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id, 'amount' => 500]);
    }

    public function test_failed_payment_does_not_activate_plan(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $authority = $this->postJson('/api/subscriptions/checkout', ['plan_key' => 'starter'])->json('data.authority');
        $headers = ['X-Payment-Signature' => hash_hmac('sha256', $authority.'|failed', 'test-secret')];

        $this->postJson('/api/webhooks/payment', compact('authority') + ['status' => 'failed'], $headers)
            ->assertOk()->assertJsonPath('data.status', 'failed');

        $this->assertDatabaseHas('payments', ['authority' => $authority, 'status' => 'failed']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_key' => 'free']);
        $this->assertDatabaseCount('credit_transactions', 0);
    }

    public function test_payment_webhook_requires_a_valid_signature(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $authority = $this->postJson('/api/subscriptions/checkout', ['plan_key' => 'starter'])->json('data.authority');

        $this->postJson('/api/webhooks/payment', ['authority' => $authority, 'status' => 'paid'], ['X-Payment-Signature' => 'invalid'])
            ->assertUnauthorized();
    }

    public function test_zarinpal_callback_verifies_and_activates_the_plan(): void
    {
        config([
            'payment.driver' => 'zarinpal',
            'payment.zarinpal.merchant_id' => 'merchant-id',
            'payment.zarinpal.callback_url' => 'https://hale.test/api/payments/zarinpal/callback',
        ]);
        Http::fake([
            'https://payment.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'A0000000000000000000000000000wwOGYpd'],
            ]),
            'https://payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 100, 'ref_id' => 987654],
            ]),
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/subscriptions/checkout', ['plan_key' => 'starter'])->assertCreated();
        $this->getJson('/api/payments/zarinpal/callback?Authority=A0000000000000000000000000000wwOGYpd&Status=OK')
            ->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_key' => 'starter']);
    }
}