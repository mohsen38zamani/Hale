<?php

namespace App\Domains\Billing\Providers;

use App\Domains\Billing\Contracts\PaymentGateway;
use App\Domains\Billing\Exceptions\PaymentGatewayException;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ZarinpalPaymentGateway implements PaymentGateway
{
    public function createPayment(User $user, string $planKey, int $amount): array
    {
        $response = $this->client()->post('/pg/v4/payment/request.json', [
            'merchant_id' => config('payment.zarinpal.merchant_id'),
            'amount' => $amount,
            'currency' => config('payment.zarinpal.currency', 'IRR'),
            'description' => 'خرید پلن '.($planKey),
            'callback_url' => config('payment.zarinpal.callback_url'),
            'metadata' => array_filter([
                'mobile' => $user->phone,
                'email' => $user->email,
                'order_id' => (string) $user->id.'-'.now()->timestamp,
            ]),
        ]);

        $payload = $response->json();
        $code = (int) data_get($payload, 'data.code', 0);
        $authority = data_get($payload, 'data.authority');
        if (! $response->successful() || $code !== 100 || ! is_string($authority) || $authority === '') {
            throw new PaymentGatewayException((string) data_get($payload, 'errors.0.message', 'خطا در ایجاد درخواست پرداخت زرین‌پال.'), $response->status());
        }

        return [
            'authority' => $authority,
            'redirect_url' => rtrim((string) config('payment.zarinpal.start_pay_url'), '/').'/'.$authority,
        ];
    }

    public function verifyPayment(string $authority, int $amount): array|false
    {
        $response = $this->client()->post('/pg/v4/payment/verify.json', [
            'merchant_id' => config('payment.zarinpal.merchant_id'),
            'amount' => $amount,
            'authority' => $authority,
        ]);
        $payload = $response->json();
        $code = (int) data_get($payload, 'data.code', 0);
        if (! $response->successful() || ! in_array($code, [100, 101], true)) {
            return false;
        }

        return ['reference' => (string) data_get($payload, 'data.ref_id')];
    }

    private function client(): PendingRequest
    {
        $merchantId = (string) config('payment.zarinpal.merchant_id');
        if ($merchantId === '') {
            throw new PaymentGatewayException('Zarinpal merchant ID is not configured.');
        }

        return Http::baseUrl((string) config('payment.zarinpal.base_url'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('payment.zarinpal.timeout', 15));
    }
}
