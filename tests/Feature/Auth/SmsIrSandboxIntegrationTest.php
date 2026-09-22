<?php

namespace Tests\Feature\Auth;

use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SmsIrSandboxIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.sms.driver' => 'sms_ir',
            'services.sms_ir.api_key' => 'sandbox-live-key-xyz',
            'services.sms_ir.template_id' => 998877,
            'services.sms_ir.base_url' => 'https://api.sms.ir',
            'services.sms_ir.code_parameter' => 'Code',
            'verification.phone.testing_code' => '654321',
            'verification.phone.free_credits' => 5,
        ]);
    }

    public function test_complete_sms_ir_otp_send_and_verify_flow(): void
    {
        $user = User::factory()->create(['phone' => null]);
        app(CreditService::class)->initialize($user, 10);

        Http::fake([
            'https://api.sms.ir/v1/send/verify' => Http::response([
                'status' => 1,
                'message' => 'موفق',
                'data' => [
                    'messageId' => 12345678,
                    'cost' => 1.5,
                ],
            ], 200),
        ]);

        Sanctum::actingAs($user);

        // 1. Send OTP code
        $sendResponse = $this->postJson('/api/auth/phone/send-code', [
            'phone' => '09129998877',
        ]);

        $sendResponse->assertOk()
            ->assertJsonPath('data.debug_code', '654321');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.sms.ir/v1/send/verify'
                && $request->header('X-API-KEY')[0] === 'sandbox-live-key-xyz'
                && $request->data()['mobile'] === '+989129998877'
                && $request->data()['templateId'] === 998877
                && $request->data()['parameters'][0]['name'] === 'Code'
                && $request->data()['parameters'][0]['value'] === '654321';
        });

        // 2. Verify OTP code
        $verifyResponse = $this->postJson('/api/auth/verify-phone', [
            'code' => '654321',
        ]);

        $verifyResponse->assertOk()
            ->assertJsonPath('data.credits_added', 5)
            ->assertJsonPath('data.user.phone', '+989129998877');

        $this->assertNotNull($user->fresh()->phone_verified_at);
        $this->assertSame(15, app(CreditService::class)->account($user)->fresh()->balance);
    }
}
