<?php

namespace App\Domains\Auth\Providers;

use App\Domains\Auth\Contracts\SmsProvider;
use App\Domains\Auth\Exceptions\SmsProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class SmsIrProvider implements SmsProvider
{
    public function sendVerification(string $mobile, string $code): array
    {
        $response = $this->client()->post('/v1/send/verify', [
            'mobile' => $mobile,
            'templateId' => (int) config('services.sms_ir.template_id'),
            'parameters' => [
                ['name' => config('services.sms_ir.code_parameter', 'Code'), 'value' => $code],
            ],
        ]);

        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload) || (int) ($payload['status'] ?? 0) !== 1) {
            $message = is_array($payload) ? (string) ($payload['message'] ?? 'SMS provider request failed.') : 'SMS provider request failed.';
            throw new SmsProviderException($message, $response->status());
        }

        return [
            'message_id' => data_get($payload, 'data.messageId'),
            'cost' => data_get($payload, 'data.cost'),
        ];
    }

    private function client(): PendingRequest
    {
        $apiKey = (string) config('services.sms_ir.api_key');
        if ($apiKey === '') {
            throw new SmsProviderException('SMS.ir API key is not configured.');
        }

        return Http::baseUrl((string) config('services.sms_ir.base_url'))
            ->acceptJson()
            ->withHeaders(['X-API-KEY' => $apiKey])
            ->timeout((int) config('services.sms_ir.timeout', 10));
    }
}