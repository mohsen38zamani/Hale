<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Exceptions\AiProviderException;
use App\Domains\AI\Providers\Google\GoogleImageEditProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoogleImageEditProviderTest extends TestCase
{
    private const SAMPLE_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_it_supports_image_edit_type_only(): void
    {
        $provider = new GoogleImageEditProvider('test-key');

        $this->assertTrue($provider->supports('image_edit'));
        $this->assertFalse($provider->supports('image'));
        $this->assertFalse($provider->supports('video', 5));
        $this->assertSame('google_image_edit', $provider->key());
    }

    public function test_it_throws_when_api_key_is_missing(): void
    {
        $provider = new GoogleImageEditProvider('');

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('Google AI API Key تنظیم نشده است.');

        $provider->generate(new GenerationInput(
            type: 'image_edit',
            prompt: 'prompt',
            aspectRatio: '1:1',
        ));
    }

    public function test_it_throws_when_source_asset_is_missing_on_disk(): void
    {
        Storage::fake('s3');
        $provider = new GoogleImageEditProvider('test-key');

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('تصویر منبع برای ویرایش در دسترس نیست.');

        $provider->generate(new GenerationInput(
            type: 'image_edit',
            prompt: 'prompt',
            aspectRatio: '1:1',
            assetDisk: 's3',
            assetPath: 'missing/photo.png',
        ));
    }

    public function test_it_generates_edit_successfully_and_sends_valid_mime(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('uploads/source.png', base64_decode(self::SAMPLE_PNG));

        $fakeOutputBase64 = base64_encode("\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR");
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'inlineData' => [
                                        'mimeType' => 'image/png',
                                        'data' => $fakeOutputBase64,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 120,
                    'candidatesTokenCount' => 250,
                ],
            ], 200),
        ]);

        $provider = new GoogleImageEditProvider('test-key');
        $result = $provider->generate(new GenerationInput(
            type: 'image_edit',
            prompt: 'Remove background completely.',
            aspectRatio: '1:1',
            assetDisk: 's3',
            assetPath: 'uploads/source.png',
        ));

        $this->assertSame('image/png', $result->mime);
        $this->assertSame('png', $result->extension);
        $this->assertSame('gemini-2.5-flash-image', $result->model);
        $this->assertSame(120, $result->inputTokens);
        $this->assertSame(250, $result->outputTokens);
        $this->assertStringStartsWith("\x89PNG", $result->contents);

        Http::assertSent(function ($request) {
            $parts = $request['contents'][0]['parts'];

            return str_contains($request->url(), 'models/gemini-2.5-flash-image:generateContent')
                && $parts[0]['inline_data']['mime_type'] === 'image/png'
                && ! empty($parts[0]['inline_data']['data'])
                && $parts[1]['text'] === 'Remove background completely.';
        });
    }

    public function test_it_throws_rate_limit_exception_on_429(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('uploads/source.png', base64_decode(self::SAMPLE_PNG));

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Rate limit exceeded'], 429),
        ]);

        $provider = new GoogleImageEditProvider('test-key');

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('محدودیت نرخ درخواست هوش مصنوعی گوگل (429) فرا رسیده است.');

        $provider->generate(new GenerationInput(
            type: 'image_edit',
            prompt: 'prompt',
            aspectRatio: '1:1',
            assetDisk: 's3',
            assetPath: 'uploads/source.png',
        ));
    }

    public function test_it_marks_5xx_as_transient(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('uploads/source.png', base64_decode(self::SAMPLE_PNG));

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response('Server error', 503),
        ]);

        $provider = new GoogleImageEditProvider('test-key');

        try {
            $provider->generate(new GenerationInput(
                type: 'image_edit',
                prompt: 'prompt',
                aspectRatio: '1:1',
                assetDisk: 's3',
                assetPath: 'uploads/source.png',
            ));
            $this->fail('Expected exception was not thrown');
        } catch (AiProviderException $e) {
            $this->assertTrue($e->retryable());
        }
    }

    public function test_it_marks_4xx_as_permanent(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('uploads/source.png', base64_decode(self::SAMPLE_PNG));

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response('Bad request', 400),
        ]);

        $provider = new GoogleImageEditProvider('test-key');

        try {
            $provider->generate(new GenerationInput(
                type: 'image_edit',
                prompt: 'prompt',
                aspectRatio: '1:1',
                assetDisk: 's3',
                assetPath: 'uploads/source.png',
            ));
            $this->fail('Expected exception was not thrown');
        } catch (AiProviderException $e) {
            $this->assertFalse($e->retryable());
        }
    }

    public function test_it_throws_permanent_exception_when_no_image_in_parts(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('uploads/source.png', base64_decode(self::SAMPLE_PNG));

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'I cannot edit this image due to policy.'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $provider = new GoogleImageEditProvider('test-key');

        try {
            $provider->generate(new GenerationInput(
                type: 'image_edit',
                prompt: 'prompt',
                aspectRatio: '1:1',
                assetDisk: 's3',
                assetPath: 'uploads/source.png',
            ));
            $this->fail('Expected exception was not thrown');
        } catch (AiProviderException $e) {
            $this->assertFalse($e->retryable());
            $this->assertStringContainsString('خروجی تصویری تولید نکرد', $e->getMessage());
        }
    }
}
