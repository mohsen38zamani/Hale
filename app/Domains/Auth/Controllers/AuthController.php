<?php

namespace App\Domains\Auth\Controllers;

use App\Domains\Auth\Exceptions\SmsProviderException;
use App\Domains\Auth\Requests\ForgotPasswordRequest;
use App\Domains\Auth\Requests\LoginRequest;
use App\Domains\Auth\Requests\RegisterRequest;
use App\Domains\Auth\Requests\ResetPasswordRequest;
use App\Domains\Auth\Requests\SendPhoneVerificationRequest;
use App\Domains\Auth\Requests\UpdateProfileRequest;
use App\Domains\Auth\Requests\VerifyPhoneRequest;
use App\Domains\Auth\Services\PhoneVerificationService;
use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Notifications\Notifications\WelcomeNotification;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request, CreditService $credits): JsonResponse
    {
        $user = User::create($request->safe()->only(['name', 'email', 'phone', 'password']));
        $credits->initialize($user);
        $user->notify(new WelcomeNotification);

        return $this->success($this->tokenPayload($user, $request->string('device_name')->value() ?: 'pwa', $credits), 201);
    }

    public function login(LoginRequest $request, CreditService $credits): JsonResponse
    {
        $identifier = $request->string('identifier')->value();
        $user = User::query()
            ->where(fn ($query) => $query->where('email', $identifier)->orWhere('phone', $identifier))
            ->first();
        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return $this->error('INVALID_CREDENTIALS', 'ایمیل یا شماره موبایل یا رمز عبور نادرست است.', 422);
        }

        return $this->success($this->tokenPayload($user, $request->string('device_name')->value() ?: 'pwa', $credits));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->success(['message' => 'با موفقیت خارج شدید.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return $this->success(['message' => 'اگر این ایمیل ثبت شده باشد، لینک بازیابی ارسال می‌شود.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            static function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error('INVALID_RESET_TOKEN', 'لینک بازیابی معتبر یا قابل استفاده نیست.', 422);
        }

        return $this->success(['message' => 'رمز عبور با موفقیت تغییر کرد.']);
    }

    public function sendPhoneVerification(SendPhoneVerificationRequest $request, PhoneVerificationService $verification): JsonResponse
    {
        try {
            return $this->success($verification->send($request->user(), $request->string('phone')->value()));
        } catch (SmsProviderException $exception) {
            return $this->error('SMS_PROVIDER_ERROR', 'ارسال پیامک انجام نشد. لطفاً دوباره تلاش کنید.', 502);
        }
    }

    public function verifyPhone(VerifyPhoneRequest $request, PhoneVerificationService $verification): JsonResponse
    {
        try {
            $credited = $verification->verify($request->user(), $request->string('code')->value());
        } catch (\RuntimeException $exception) {
            return $this->error('PHONE_VERIFICATION_FAILED', $exception->getMessage(), 422);
        }

        return $this->success([
            'user' => $request->user()->fresh()->only(['id', 'name', 'email', 'phone', 'phone_verified_at']),
            'credits_added' => $credited,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request, CreditService $credits): JsonResponse
    {
        $user = $request->user();

        if ($request->filled('password') && ! Hash::check($request->string('current_password'), $user->password)) {
            return $this->error('INVALID_CURRENT_PASSWORD', 'رمز عبور فعلی نادرست است.', 422);
        }

        $user->fill($request->safe()->only(['name', 'email', 'password']))->save();

        return $this->success($this->userPayload($user->fresh(), $credits));
    }

    private function tokenPayload(User $user, string $deviceName, CreditService $credits): array
    {
        return ['user' => $this->userPayload($user, $credits), 'token' => $user->createToken($deviceName)->plainTextToken];
    }

    private function userPayload(User $user, CreditService $credits): array
    {
        app(SubscriptionService::class)->syncExpired($user);
        $user->refresh();

        return [...$user->only(['id', 'name', 'email', 'phone', 'plan_key']), 'credits_balance' => $credits->account($user)->balance];
    }
}
