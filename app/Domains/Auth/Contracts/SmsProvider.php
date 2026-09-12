<?php

namespace App\Domains\Auth\Contracts;

interface SmsProvider
{
    /**
     * @return array{message_id: int|string|null, cost: float|int|null}
     */
    public function sendVerification(string $mobile, string $code): array;
}