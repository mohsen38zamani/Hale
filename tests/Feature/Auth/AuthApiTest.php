<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Routing\Middleware\ThrottleRequests;
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
        $token = $this->postJson('/api/auth/login', ['email' => 'sara@example.com', 'password' => 'Secret123'])->json('data.token');
        $this->withToken($token)->getJson('/api/user/profile')->assertOk()->assertJsonPath('data.email', 'sara@example.com');
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
}
