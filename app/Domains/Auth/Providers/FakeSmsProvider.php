<?php

namespace App\Domains\Auth\Providers;

use App\Domains\Auth\Contracts\SmsProvider;

class FakeSmsProvider implements SmsProvider
{
    public function sendVerification(string $mobile, string $code): array
    {
        return ['message_id' => 'fake-'.hash('sha256', $mobile.$code), 'cost' => 0];
    }
}