<?php

namespace Tests\Unit\Auth;

use App\Domains\Auth\Exceptions\SmsProviderException;
use App\Domains\Auth\Providers\SmsIrProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsIrProviderTest extends TestCase
{
    public function test_it_sends_a_verify_request_to_sms_ir(): void
    {
        config([
            'services.sms_ir.api_key' => 'test-api-key',
            'services.sms_ir.template_id' => 123456,
            'services.sms_ir.base_url' => 'https://api.sms.ir',
        ]);
        Http::fake([
            'https://api.sms.ir/v1/send/verify' => Http::response([
                'status' => 1,
                'message' => 'موفق',
                'data' => ['messageId' => 89545112, 'cost' => 1.0],
            ], 200),
        ]);

        $result = app(SmsIrProvider::class)->sendVerification('+989121234567', '123456');

        $this->assertSame(89545112, $result['message_id']);
        $this->assertEquals(1.0, $result['cost']);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.sms.ir/v1/send/verify'
                && $request->header('X-API-KEY')[0] === 'test-api-key'
                && $request->data() === [
                    'mobile' => '+989121234567',
                    'templateId' => 123456,
                    'parameters' => [['name' => 'Code', 'value' => '123456']],
                ];
        });
    }

    public function test_it_raises_an_exception_for_a_failed_sms_ir_response(): void
    {
        config(['services.sms_ir.api_key' => 'test-api-key']);
        Http::fake([
            'https://api.sms.ir/v1/send/verify' => Http::response([
                'status' => 113,
                'message' => 'قالب یافت نشد',
            ], 400),
        ]);

        $this->expectException(SmsProviderException::class);
        app(SmsIrProvider::class)->sendVerification('+989121234567', '123456');
    }
}
