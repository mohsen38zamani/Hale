<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Providers\Google\GoogleVeoProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleVeoProviderTest extends TestCase
{
    public function test_it_supports_video_with_specific_durations_only(): void
    {
        $provider = new GoogleVeoProvider('test-api-key');

        $this->assertFalse($provider->supports('image'));
        $this->assertTrue($provider->supports('video', 5));
        $this->assertTrue($provider->supports('video', 8));
        $this->assertTrue($provider->supports('video', 10));
        $this->assertFalse($provider->supports('video', 15));
        $this->assertSame('google_veo', $provider->key());
    }

    public function test_it_throws_when_api_key_is_missing(): void
    {
        $provider = new GoogleVeoProvider('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google AI API Key تنظیم نشده است.');

        $provider->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
    }

    public function test_it_generates_video_successfully_with_http_fake(): void
    {
        // MP4 standard header starts with 4 bytes size + "ftyp" + brand
        $fakeMp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";
        $fakeMp4Base64 = base64_encode($fakeMp4);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'predictions' => [
                    ['bytesBase64Encoded' => $fakeMp4Base64],
                ],
            ], 200),
        ]);

        $provider = new GoogleVeoProvider('fake-key');
        $result = $provider->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 8));

        $this->assertSame('video/mp4', $result->mime);
        $this->assertSame('mp4', $result->extension);
        $this->assertSame('veo-2.0-generate-001', $result->model);
        $this->assertSame(0.40, $result->costUsd); // 8 * 0.05
        $this->assertSame('ftyp', substr($result->contents, 4, 4));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/veo-2.0-generate-001:predictVideo')
                && $request['parameters']['durationSeconds'] === 8
                && $request['parameters']['aspectRatio'] === '9:16';
        });
    }

    public function test_it_handles_rate_limit_429_error(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Resource exhausted'], 429),
        ]);

        $provider = new GoogleVeoProvider('fake-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('محدودیت نرخ درخواست هوش مصنوعی گوگل (429) فرا رسیده است.');

        $provider->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
    }
}
