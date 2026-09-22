<?php

namespace Tests\Feature\Billing;

use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSecurityReplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payment.driver' => 'fake']);
        config(['payment.webhook_secret' => 'super-secret-test-key']);
    }

    public function test_payment_webhook_replay_does_not_double_credit_or_duplicate_invoice(): void
    {
        config(['payment.webhook_secret' => 'super-secret-test-key']);

        $user = User::factory()->create(['plan_key' => 'free']);
        $credits = app(CreditService::class);
        $credits->initialize($user, 0);

        $authority = 'fake-AUTH-TEST-123456';
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'amount' => 5900000,
            'gateway' => 'fake',
            'status' => 'pending',
            'authority' => $authority,
            'idempotency_key' => 'checkout:'.$user->id.':test-key-1',
        ]);

        $signature = hash_hmac('sha256', $authority.'|paid', 'super-secret-test-key');

        // First webhook call
        $firstCall = $this->withHeaders([
            'X-Payment-Signature' => $signature,
        ])->postJson('/api/webhooks/payment', [
            'authority' => $authority,
            'status' => 'paid',
        ]);

        $firstCall->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('starter', $user->fresh()->plan_key);
        $this->assertSame(200, $credits->account($user)->balance);
        $this->assertSame(1, $payment->invoice()->count());
        $this->assertSame(1, Subscription::query()->where('user_id', $user->id)->count());

        // Replay of the exact same webhook request
        $replayCall = $this->withHeaders([
            'X-Payment-Signature' => $signature,
        ])->postJson('/api/webhooks/payment', [
            'authority' => $authority,
            'status' => 'paid',
        ]);

        $replayCall->assertOk()
            ->assertJsonPath('data.status', 'paid');

        // Verify no double-crediting, no duplicate subscription, no duplicate invoice
        $this->assertSame(200, $credits->account($user)->fresh()->balance);
        $this->assertSame(1, $payment->invoice()->count());
        $this->assertSame(1, Subscription::query()->where('user_id', $user->id)->count());
    }

    public function test_callback_after_webhook_does_not_double_credit(): void
    {
        config(['payment.webhook_secret' => 'super-secret-test-key']);

        $user = User::factory()->create(['plan_key' => 'free']);
        $credits = app(CreditService::class);
        $credits->initialize($user, 0);

        $authority = 'fake-AUTH-CALLBACK-REPLAY-789';
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'amount' => 5900000,
            'gateway' => 'fake',
            'status' => 'pending',
            'authority' => $authority,
            'idempotency_key' => 'checkout:'.$user->id.':test-key-2',
        ]);

        $signature = hash_hmac('sha256', $authority.'|paid', 'super-secret-test-key');

        // Webhook settles first
        $this->withHeaders(['X-Payment-Signature' => $signature])
            ->postJson('/api/webhooks/payment', [
                'authority' => $authority,
                'status' => 'paid',
            ])->assertOk();

        $this->assertSame(200, $credits->account($user)->fresh()->balance);

        // User browser callback arrives afterwards
        $callback = $this->getJson('/api/payments/zarinpal/callback?Authority='.$authority.'&Status=OK');
        $callback->assertOk()
            ->assertJsonPath('data.status', 'paid');

        // Balance remains 150 (not 300)
        $this->assertSame(200, $credits->account($user)->fresh()->balance);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        config(['payment.webhook_secret' => 'super-secret-test-key']);

        $user = User::factory()->create();
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'amount' => 5900000,
            'gateway' => 'fake',
            'status' => 'pending',
            'authority' => 'AUTH-FORGERY',
            'idempotency_key' => 'checkout:'.$user->id.':test-key-3',
        ]);

        $response = $this->withHeaders([
            'X-Payment-Signature' => 'invalid-hmac-signature',
        ])->postJson('/api/webhooks/payment', [
            'authority' => 'AUTH-FORGERY',
            'status' => 'paid',
        ]);

        $response->assertStatus(401);
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_failed_callback_marks_payment_failed_without_granting_credits(): void
    {
        $user = User::factory()->create(['plan_key' => 'free']);
        $credits = app(CreditService::class);
        $credits->initialize($user, 0);

        $authority = 'AUTH-FAILED-CALLBACK';
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'amount' => 5900000,
            'gateway' => 'fake',
            'status' => 'pending',
            'authority' => $authority,
            'idempotency_key' => 'checkout:'.$user->id.':test-key-4',
        ]);

        $response = $this->getJson('/api/payments/zarinpal/callback?Authority='.$authority.'&Status=NOK');
        $response->assertOk()
            ->assertJsonPath('data.status', 'failed');

        $this->assertSame('failed', $payment->fresh()->status);
        $this->assertSame(0, $credits->account($user)->fresh()->balance);
        $this->assertSame('free', $user->fresh()->plan_key);
    }
}
