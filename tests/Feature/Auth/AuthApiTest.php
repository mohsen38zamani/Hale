<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use App\Domains\Auth\Contracts\SmsProvider;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_user_can_register_and_receive_token(): void
    {
        $this->postJson('/api/auth/register', ['name' => 'سارا', 'email' => 'sara@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])->assertCreated()->assertJsonPath('success', true)->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_user_can_login_and_access_profile(): void
    {
        User::factory()->create(['email' => 'sara@example.com', 'password' => 'Secret123']);
        $token = $this->postJson('/api/auth/login', ['identifier' => 'sara@example.com', 'password' => 'Secret123'])->json('data.token');
        $this->withToken($token)->getJson('/api/user/profile')->assertOk()->assertJsonPath('data.email', 'sara@example.com');
    }

    public function test_user_can_register_without_email_and_login_with_mobile(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'مریم',
            'phone' => '+989121234567',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertCreated()->assertJsonPath('data.user.phone', '+989121234567');

        $this->postJson('/api/auth/login', [
            'identifier' => '+989121234567',
            'password' => 'Secret123',
        ])->assertOk()->assertJsonPath('data.user.phone', '+989121234567');
    }

    public function test_registration_requires_email_or_mobile(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'کاربر بدون شناسه',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_mobile_number_must_be_unique(): void
    {
        User::factory()->create(['phone' => '+989121234567']);

        $this->postJson('/api/auth/register', [
            'name' => 'کاربر دوم',
            'phone' => '+989121234567',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_invalid_registration_uses_standard_error_envelope(): void
    {
        $this->postJson('/api/auth/register', [])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'sara@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'sara@example.com'])
            ->assertOk()
            ->assertJsonPath('data.message', 'اگر این ایمیل ثبت شده باشد، لینک بازیابی ارسال می‌شود.');

        Notification::assertSentTo(User::where('email', 'sara@example.com')->first(), ResetPassword::class);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'sara@example.com', 'password' => 'OldSecret123']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertOk()->assertJsonPath('data.message', 'رمز عبور با موفقیت تغییر کرد.');

        $this->assertTrue(password_verify('NewSecret123', $user->fresh()->password));
    }

    public function test_invalid_password_reset_token_uses_standard_error_envelope(): void
    {
        User::factory()->create(['email' => 'sara@example.com']);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'sara@example.com',
            'token' => 'invalid-token',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_RESET_TOKEN');
    }

    public function test_authenticated_user_can_update_profile_and_password(): void
    {
        $user = User::factory()->create(['email' => 'sara@example.com', 'password' => 'OldSecret123']);
        $token = $this->postJson('/api/auth/login', ['identifier' => $user->email, 'password' => 'OldSecret123'])->json('data.token');

        $this->withToken($token)->putJson('/api/user/profile', [
            'name' => 'سارا جدید',
            'email' => 'new@example.com',
            'current_password' => 'OldSecret123',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertOk()->assertJsonPath('data.email', 'new@example.com');

        $this->assertTrue(password_verify('NewSecret123', $user->fresh()->password));
    }

    public function test_profile_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => 'OldSecret123']);
        Sanctum::actingAs($user);

        $this->putJson('/api/user/profile', [
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
            'current_password' => 'WrongSecret123',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_CURRENT_PASSWORD');
    }

    public function test_user_can_verify_mobile_and_receive_free_credits_once(): void
    {
        config(['verification.phone.testing_code' => '123456']);
        $user = User::factory()->create(['phone' => null]);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/phone/send-code', ['phone' => '+989121234567'])
            ->assertOk()
            ->assertJsonPath('data.debug_code', '123456');

        $this->postJson('/api/auth/verify-phone', ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.credits_added', 30)
            ->assertJsonPath('data.user.phone_verified_at', fn ($value) => $value !== null);

        $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => 30]);
        $this->assertDatabaseHas('credit_transactions', ['user_id' => $user->id, 'type' => 'bonus', 'amount' => 30]);
    }

    public function test_phone_bonus_is_idempotent_and_invalid_code_is_rejected(): void
    {
        config(['verification.phone.testing_code' => '123456']);
        $user = User::factory()->create(['phone' => '+989121234567']);
        Sanctum::actingAs($user);
        $this->postJson('/api/auth/phone/send-code', ['phone' => $user->phone]);

        $this->postJson('/api/auth/verify-phone', ['code' => '000000'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'PHONE_VERIFICATION_FAILED');
        $this->postJson('/api/auth/verify-phone', ['code' => '123456'])->assertOk()->assertJsonPath('data.credits_added', 30);
        $this->postJson('/api/auth/verify-phone', ['code' => '123456'])->assertUnprocessable();
        $this->assertDatabaseCount('credit_transactions', 1);
    }

    public function test_phone_verification_uses_the_bound_sms_provider(): void
    {
        config(['verification.phone.testing_code' => '123456']);
        $provider = new class implements SmsProvider {
            public bool $sent = false;

            public function sendVerification(string $mobile, string $code): array
            {
                $this->sent = $mobile === '+989121234567' && $code === '123456';

                return ['message_id' => 'test-message', 'cost' => 0];
            }
        };
        app()->bind(SmsProvider::class, fn (): SmsProvider => $provider);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/phone/send-code', ['phone' => '+989121234567'])->assertOk();
        $this->assertTrue($provider->sent);
    }

    public function test_sms_provider_failure_uses_a_standard_api_error(): void
    {
        app()->bind(SmsProvider::class, function (): SmsProvider {
            return new class implements SmsProvider {
                public function sendVerification(string $mobile, string $code): array
                {
                    throw new \App\Domains\Auth\Exceptions\SmsProviderException('provider unavailable');
                }
            };
        });
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/phone/send-code', ['phone' => '+989121234567'])
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'SMS_PROVIDER_ERROR');
    }
}
