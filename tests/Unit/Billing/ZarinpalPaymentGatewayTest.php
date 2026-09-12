<?php

namespace Tests\Unit\Billing;

use App\Domains\Billing\Providers\ZarinpalPaymentGateway;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZarinpalPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'payment.zarinpal.merchant_id' => 'merchant-id',
            'payment.zarinpal.base_url' => 'https://payment.zarinpal.com',
            'payment.zarinpal.start_pay_url' => 'https://payment.zarinpal.com/pg/StartPay',
            'payment.zarinpal.callback_url' => 'https://hale.test/api/payments/zarinpal/callback',
        ]);
    }

    public function test_it_creates_a_zarinpal_payment_request(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com', 'phone' => '+989121234567']);
        Http::fake([
            'https://payment.zarinpal.com/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'A0000000000000000000000000000wwOGYpd'],
                'errors' => [],
            ]),
        ]);

        $result = app(ZarinpalPaymentGateway::class)->createPayment($user, 'starter', 4_990_000);

        $this->assertSame('A0000000000000000000000000000wwOGYpd', $result['authority']);
        $this->assertSame('https://payment.zarinpal.com/pg/StartPay/A0000000000000000000000000000wwOGYpd', $result['redirect_url']);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://payment.zarinpal.com/pg/v4/payment/request.json'
                && $request->data()['merchant_id'] === 'merchant-id'
                && $request->data()['amount'] === 4_990_000
                && $request->data()['callback_url'] === 'https://hale.test/api/payments/zarinpal/callback'
                && $request->data()['metadata']['mobile'] === '+989121234567';
        });
    }

    public function test_it_verifies_zarinpal_codes_100_and_101(): void
    {
        Http::fake([
            'https://payment.zarinpal.com/pg/v4/payment/verify.json' => Http::sequence()
                ->push(['data' => ['code' => 100, 'ref_id' => 12345]])
                ->push(['data' => ['code' => 101, 'ref_id' => 12345]]),
        ]);
        $gateway = app(ZarinpalPaymentGateway::class);

        $this->assertSame(['reference' => '12345'], $gateway->verifyPayment('authority', 4_990_000));
        $this->assertSame(['reference' => '12345'], $gateway->verifyPayment('authority', 4_990_000));
    }
}