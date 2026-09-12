<?php

namespace App\Domains\Billing\Contracts;

use App\Models\User;

interface PaymentGateway
{
    /** @return array{authority: string, redirect_url: string} */
    public function createPayment(User $user, string $planKey, int $amount): array;

    /** @return array{reference: string}|false */
    public function verifyPayment(string $authority, int $amount): array|false;
}