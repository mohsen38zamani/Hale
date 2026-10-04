<?php

namespace Tests\Feature\Billing;

use App\Domains\Billing\Models\Payment;
use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditTopupTest extends TestCase
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

    public function test_packs_endpoint_lists_credit_packs(): void
    {
        $packs = $this->getJson('/api/credits/packs')->assertOk()->json('data');

        $this->assertCount(3, $packs);
        $this->assertSame('topup_50', $packs[0]['key']);
        $this->assertSame(50, $packs[0]['credits']);
        $this->assertSame(1_500_000, $packs[0]['price_irr']);
    }

    public function test_topup_checkout_creates_pending_payment_without_subscription(): void
    {
        $this->fakeGateway();
        $user = User::factory()->create(['plan_key' => 'free']);

        $authority = $this->checkout($user);

        $payment = Payment::query()->where('authority', $authority)->firstOrFail();
        $this->assertSame('topup_50', $payment->plan_key);
        $this->assertSame(1_500_000, $payment->amount);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('topup', $payment->metadata['kind']);
        $this->assertSame('topup_50', $payment->metadata['pack']);
        $this->assertSame(50, $payment->metadata['credits']);

        $this->assertSame('free', $user->fresh()->plan_key);
        $this->assertSame(0, $user->subscriptions()->count());
        $this->assertSame(config('credits.initial_balance'), app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_topup_callback_grants_credits_without_changing_plan(): void
    {
        $authority = $this->fakeGateway();
        $user = User::factory()->create(['plan_key' => 'free']);
        // Registration seeds the wallet with the initial balance.
        app(CreditService::class)->initialize($user, (int) config('credits.initial_balance'));
        $this->checkout($user);

        $this->get("/api/payments/zarinpal/callback?Authority={$authority}&Status=OK")
            ->assertRedirect('/pricing?payment=paid');

        $payment = Payment::query()->where('authority', $authority)->firstOrFail();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('987654321', $payment->reference);
        $this->assertNotNull($payment->invoice);

        // 30 initial balance + the 50-credit pack.
        $this->assertSame(config('credits.initial_balance') + 50, app(CreditService::class)->account($user)->fresh()->balance);

        $account = app(CreditService::class)->account($user);
        $purchase = $account->transactions()
            ->where('type', 'purchase')
            ->where('idempotency_key', 'payment:'.$payment->id)
            ->first();
        $this->assertNotNull($purchase);
        $this->assertSame(50, (int) $purchase->amount);

        // The wallet settles alone: plan and subscriptions are untouched.
        $this->assertSame('free', $user->fresh()->plan_key);
        $this->assertSame(0, $user->subscriptions()->count());

        // A replayed callback must not grant the pack twice.
        $this->get("/api/payments/zarinpal/callback?Authority={$authority}&Status=OK");
        $this->assertSame(config('credits.initial_balance') + 50, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_topup_failed_callback_grants_nothing(): void
    {
        $authority = $this->fakeGateway();
        $user = User::factory()->create(['plan_key' => 'free']);
        $this->checkout($user);

        $this->get("/api/payments/zarinpal/callback?Authority={$authority}&Status=NOK")
            ->assertRedirect('/pricing?payment=failed');

        $this->assertSame('failed', Payment::query()->where('authority', $authority)->firstOrFail()->status);
        $this->assertSame(config('credits.initial_balance'), app(CreditService::class)->account($user)->fresh()->balance);
        $this->assertSame(0, $user->subscriptions()->count());
    }

    public function test_topup_checkout_is_idempotent_per_key(): void
    {
        $this->fakeGateway();
        $user = User::factory()->create();

        $first = $this->checkout($user, 'stable-topup-key');
        $second = $this->checkout($user, 'stable-topup-key');

        $this->assertSame($first, $second);
        $this->assertSame(1, Payment::query()->count());
        // The second call reuses the stored authority: one gateway request total.
        Http::assertSentCount(1);
    }

    public function test_topup_rejects_unknown_pack(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/credits/topup', ['pack' => 'topup_999'])->assertUnprocessable();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_topup_requires_authentication(): void
    {
        $this->postJson('/api/credits/topup', ['pack' => 'topup_50'])->assertUnauthorized();
    }

    private function fakeGateway(): string
    {
        $authority = 'A0000000000000000000000000000TOPUP';

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

        return $authority;
    }

    private function checkout(User $user, string $idempotencyKey = 'topup-key-1'): string
    {
        Sanctum::actingAs($user);

        $response = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/credits/topup', ['pack' => 'topup_50']);
        $response->assertStatus(201);

        return (string) $response->json('data.authority');
    }
}
