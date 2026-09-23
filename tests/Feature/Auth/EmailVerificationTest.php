<?php

namespace Tests\Feature\Auth;

use App\Domains\Notifications\Notifications\VerifyEmailNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function validGenerationPayload(User $user): array
    {
        $product = $user->products()->create(['name' => 'عطر']);

        return [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'environment' => 'studio',
        ];
    }

    public function test_register_with_email_sends_verification_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'سارا',
            'email' => 'sara@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertCreated();

        Notification::assertSentTo(User::query()->first(), VerifyEmailNotification::class);
    }

    public function test_register_without_email_skips_verification_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', [
            'name' => 'مریم',
            'phone' => '+989121234567',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ])->assertCreated();

        Notification::assertNotSentTo(User::query()->first(), VerifyEmailNotification::class);
    }

    public function test_unverified_user_cannot_create_generation(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/generations', $this->validGenerationPayload($user))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');

        $this->assertSame(0, $user->generations()->count());
    }

    public function test_unverified_user_cannot_checkout(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/subscriptions/checkout', ['plan_key' => 'starter'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');
    }

    public function test_verified_user_can_create_generation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/generations', $this->validGenerationPayload($user))
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued');
    }

    public function test_phone_only_user_is_exempt_from_email_verification(): void
    {
        $user = User::factory()->create(['email' => null, 'email_verified_at' => null]);
        Sanctum::actingAs($user);

        $this->assertFalse($user->requiresEmailVerification());

        $this->postJson('/api/generations', $this->validGenerationPayload($user))
            ->assertStatus(202);
    }

    public function test_unverified_user_can_still_read_profile(): void
    {
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/user/profile')->assertOk();
    }

    public function test_resend_endpoint_sends_the_notification_again(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('data.message', 'لینک تأیید ایمیل دوباره ارسال شد. صندوق ایمیل (و پوشه اسپم) را بررسی کنید.');

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_is_rejected_for_verified_and_phone_only_accounts(): void
    {
        $verified = User::factory()->create();
        Sanctum::actingAs($verified);
        $this->postJson('/api/auth/email/verification-notification')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'ALREADY_VERIFIED');

        $this->app['auth']->forgetGuards();

        $phoneOnly = User::factory()->create(['email' => null]);
        Sanctum::actingAs($phoneOnly);
        $this->postJson('/api/auth/email/verification-notification')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'NO_EMAIL_ADDRESS');
    }

    public function test_valid_signed_url_marks_email_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('email.verification.verify', now()->addDay(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertOk();

        $this->assertNotNull($user->fresh()->email_verified_at);

        Sanctum::actingAs($user->fresh());
        $this->postJson('/api/generations', $this->validGenerationPayload($user))->assertStatus(202);
    }

    public function test_tampered_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('email.verification.verify', now()->addDay(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
        $tampered = str_replace(sha1($user->email), sha1('other@example.com'), $url);

        $this->get($tampered)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'INVALID_VERIFICATION_LINK');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_expired_signature_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('email.verification.verify', now()->subMinutes(5), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'EXPIRED_VERIFICATION_LINK');

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_changing_profile_email_requires_reverification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'old@example.com', 'password' => 'Secret123']);
        Sanctum::actingAs($user);

        $this->putJson('/api/user/profile', [
            'email' => 'new@example.com',
            'current_password' => 'Secret123',
        ])->assertOk()->assertJsonPath('data.email', 'new@example.com');

        $fresh = $user->fresh();
        $this->assertNull($fresh->email_verified_at, 'Changing the email must reset verification.');
        Notification::assertSentTo($fresh, VerifyEmailNotification::class);

        // Production PHP-FPM rebuilds guards per request; the test guard still
        // holds the pre-update user instance, so flush and re-authenticate.
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($fresh);

        $this->postJson('/api/generations', $this->validGenerationPayload($user))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED');
    }
}
