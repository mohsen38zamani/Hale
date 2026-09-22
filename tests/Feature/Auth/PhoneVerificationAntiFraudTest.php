<?php

namespace Tests\Feature\Auth;

use App\Domains\Auth\Contracts\SmsProvider;
use App\Domains\Auth\Models\PhoneVerificationCode;
use App\Domains\Credits\Models\CreditAccount;
use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhoneVerificationAntiFraudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['verification.phone.testing_code' => '123456']);
        config(['verification.phone.free_credits' => 5]);
    }

    public function test_normalizer_converts_persian_and_iranian_formats_to_e164(): void
    {
        $this->assertSame('+989121234567', PhoneNormalizer::normalize('09121234567'));
        $this->assertSame('+989121234567', PhoneNormalizer::normalize('۰۹۱۲۱۲۳۴۵۶۷'));
        $this->assertSame('+989121234567', PhoneNormalizer::normalize('٠٩١٢١٢٣٤٥٦٧'));
        $this->assertSame('+989121234567', PhoneNormalizer::normalize('00989121234567'));
        $this->assertSame('+989121234567', PhoneNormalizer::normalize('+989121234567'));
        $this->assertSame('+989121234567', PhoneNormalizer::normalize(' 0912-123-4567 '));
    }

    public function test_cannot_register_duplicate_phone_number_in_any_format(): void
    {
        // First user registers with standard E.164
        User::factory()->create([
            'phone' => '+989121234567',
        ]);

        // Second user attempts to register with 09...
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate User 1',
            'phone' => '09121234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        // Third user attempts to register with Persian digits
        $response2 = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate User 2',
            'phone' => '۰۹۱۲۱۲۳۴۵۶۷',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $response2->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        // Fourth user attempts with 0098...
        $response3 = $this->postJson('/api/auth/register', [
            'name' => 'Duplicate User 3',
            'phone' => '00989121234567',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $response3->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_user_cannot_request_verification_for_phone_already_owned_by_another_user(): void
    {
        User::factory()->create([
            'phone' => '+989121112233',
        ]);

        $anotherUser = User::factory()->create([
            'phone' => null,
        ]);

        // Trying with Persian numbers of the same phone
        $response = $this->actingAs($anotherUser, 'sanctum')->postJson('/api/auth/phone/send-code', [
            'phone' => '۰۹۱۲۱۱۱۲۲۳۳',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_phone_bonus_credits_can_only_be_claimed_once_per_phone_globally(): void
    {
        $user1 = User::factory()->create(['phone' => null]);
        $user2 = User::factory()->create(['phone' => null]);

        $credits = app(CreditService::class);
        $credits->initialize($user1, 10);
        $credits->initialize($user2, 10);

        $targetPhone = '+989129876543';

        // User 1 verifies phone and claims 5 free credits
        $this->actingAs($user1, 'sanctum')->postJson('/api/auth/phone/send-code', [
            'phone' => '09129876543',
        ])->assertOk();

        $verify1 = $this->actingAs($user1, 'sanctum')->postJson('/api/auth/verify-phone', [
            'code' => '123456',
        ]);
        $verify1->assertOk()
            ->assertJsonPath('data.credits_added', 5);

        $account1 = $credits->account($user1);
        $this->assertEquals(15, $account1->balance);

        // Imagine user1 releases/changes phone or user2 attempts verification on same phone
        // Reset user 1 phone so DB unique check wouldn't block user 2 at database level
        $user1->update(['phone' => '+989120000000']);

        $this->actingAs($user2, 'sanctum')->postJson('/api/auth/phone/send-code', [
            'phone' => '۰۹۱۲۹۸۷۶۵۴۳',
        ])->assertOk();

        $verify2 = $this->actingAs($user2, 'sanctum')->postJson('/api/auth/verify-phone', [
            'code' => '123456',
        ]);
        $verify2->assertOk()
            ->assertJsonPath('data.credits_added', 0); // Anti-fraud bonus returns 0, no duplicate bonus!

        $account2 = $credits->account($user2);
        $this->assertEquals(10, $account2->balance);
    }

    public function test_sms_send_rate_limiter_throttles_excessive_requests_to_same_phone(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $targetPhone = '09123334455';

        // First 3 requests should pass
        for ($i = 0; $i < 3; $i++) {
            $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/phone/send-code', [
                'phone' => $targetPhone,
            ]);
            $response->assertOk();
        }

        // 4th request must be rate limited (429)
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/auth/phone/send-code', [
            'phone' => $targetPhone,
        ]);
        $response->assertStatus(429);
    }
}
