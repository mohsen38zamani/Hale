<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Contracts\SmsProvider;
use App\Domains\Auth\Models\PhoneVerificationCode;
use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PhoneVerificationService
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly SmsProvider $sms,
    ) {}

    public function send(User $user, string $phone): array
    {
        $code = config('verification.phone.testing_code') ?: str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes((int) config('verification.phone.expires_minutes'));

        PhoneVerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->delete();

        $user->forceFill(['phone' => $phone, 'phone_verified_at' => null])->save();
        PhoneVerificationCode::create([
            'user_id' => $user->id,
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => $expiresAt,
        ]);

        $this->sms->sendVerification($phone, $code);

        Log::info('Phone verification code generated', ['user_id' => $user->id, 'phone' => $phone, 'code' => $code]);

        return ['expires_at' => $expiresAt, 'debug_code' => app()->environment(['local', 'testing']) ? $code : null];
    }

    public function verify(User $user, string $code): int
    {
        $verification = PhoneVerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if ($verification === null || $verification->expires_at->isPast()) {
            throw new RuntimeException('کد تأیید منقضی یا نامعتبر است.');
        }

        if ($verification->attempts >= (int) config('verification.phone.max_attempts')) {
            throw new RuntimeException('تعداد تلاش‌های مجاز برای این کد تمام شده است.');
        }

        $verification->increment('attempts');
        if (! Hash::check($code, $verification->code_hash)) {
            throw new RuntimeException('کد تأیید نادرست است.');
        }

        $verification->update(['verified_at' => now()]);
        $user->forceFill(['phone' => $verification->phone, 'phone_verified_at' => now()])->save();

        return $this->credits->grantBonus($user, (int) config('verification.phone.free_credits'), 'phone_verification_bonus');
    }
}