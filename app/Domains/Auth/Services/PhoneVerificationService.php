<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Contracts\SmsProvider;
use App\Domains\Auth\Models\PhoneVerificationCode;
use App\Domains\Credits\Models\CreditTransaction;
use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $phone = PhoneNormalizer::normalize($phone);

        if (User::query()->where('phone', $phone)->where('id', '!=', $user->id)->exists()) {
            throw new RuntimeException('این شماره موبایل قبلاً توسط کاربر دیگری ثبت شده است.');
        }

        $code = config('verification.phone.testing_code') ?: str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = Carbon::now()->addMinutes((int) config('verification.phone.expires_minutes'));

        return DB::transaction(function () use ($user, $phone, $code, $expiresAt): array {
            PhoneVerificationCode::query()
                ->where('user_id', $user->id)
                ->whereNull('verified_at')
                ->delete();

            PhoneVerificationCode::create([
                'user_id' => $user->id,
                'phone' => $phone,
                'code_hash' => Hash::make($code),
                'expires_at' => $expiresAt,
            ]);

            $this->sms->sendVerification($phone, $code);
            $user->forceFill(['phone' => $phone, 'phone_verified_at' => null])->save();
            Log::info('Phone verification requested', ['user_id' => $user->id, 'phone_suffix' => substr($phone, -4)]);

            return ['expires_at' => $expiresAt, 'debug_code' => app()->environment(['local', 'testing']) ? $code : null];
        });
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

        $phone = PhoneNormalizer::normalize($verification->phone);

        if (User::query()->where('phone', $phone)->where('id', '!=', $user->id)->exists()) {
            throw new RuntimeException('این شماره موبایل قبلاً توسط کاربر دیگری ثبت شده است.');
        }

        $verification->update(['verified_at' => now()]);
        $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();

        $alreadyClaimed = CreditTransaction::query()
            ->where('type', 'bonus')
            ->where('idempotency_key', 'phone_bonus:'.$phone)
            ->exists();

        if ($alreadyClaimed) {
            return 0;
        }

        return $this->credits->grantBonus($user, (int) config('verification.phone.free_credits'), 'phone_bonus:'.$phone);
    }
}
