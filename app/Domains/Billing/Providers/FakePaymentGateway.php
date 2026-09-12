<?php

namespace App\Domains\Billing\Providers;

use App\Domains\Billing\Contracts\PaymentGateway;
use App\Models\User;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGateway
{
    public function createPayment(User $user, string $planKey, int $amount): array
    {
        $authority = 'fake-'.Str::uuid();

        return [
            'authority' => $authority,
            'redirect_url' => url('/fake-checkout/'.$authority),
        ];
    }

    public function verifyPayment(string $authority, int $amount): array|false
    {
        if (! str_starts_with($authority, 'fake-')) {
            return false;
        }

        return ['reference' => 'ref-'.substr(hash('sha256', $authority.$amount), 0, 16)];
    }
}