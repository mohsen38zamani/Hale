<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Exceptions\AiProviderException;
use App\Domains\AI\Providers\Google\GoogleImagenProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class GoogleImagenProviderTest extends TestCase
{
    public function test_it_supports_image_type_only(): void
    {
        $provider = new GoogleImagenProvider('test-api-key');

        $this->assertTrue($provider->supports($this->input('image')));
        $this->assertFalse($provider->supports($this->input('video', 5)));
        $this->assertFalse($provider->supports($this->input('image_edit')));
        $this->assertSame('google_imagen', $provider->key());
    }

    private function input(string $type, ?int $durationSeconds = null, ?string $assetDisk = null, ?string $assetPath = null): GenerationInput
    {
        return new GenerationInput($type, 'prompt', '1:1', $durationSeconds, $assetDisk, $assetPath);
    }

    public function test_it_throws_when_api_key_is_missing(): void
    {
        $provider = new GoogleImagenProvider('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google AI API Key تنظیم نشده است.');

        $provider->generate(new GenerationInput('image', 'luxury perfume', '1:1'));
    }

    public function test_it_generates_image_successfully_with_http_fake(): void
    {
        $fakePngBase64 = base64_encode("\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR");
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'predictions' => [
                    ['bytesBase64Encoded' => $fakePngBase64],
                ],
            ], 200),
        ]);

        $provider = new GoogleImagenProvider('fake-key');
        $result = $provider->generate(new GenerationInput('image', 'luxury perfume', '1:1'));

        $this->assertSame('image/png', $result->mime);
        $this->assertSame('png', $result->extension);
        $this->assertSame('imagen-3.0-generate-002', $result->model);
        $this->assertSame(0.04, $result->costUsd);
        $this->assertStringStartsWith("\x89PNG", $result->contents);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/imagen-3.0-generate-002:predict')
                && $request['parameters']['aspectRatio'] === '1:1'
                && $request['instances'][0]['prompt'] === 'luxury perfume';
        });
    }

    public function test_it_never_sends_a_reference_image_predict_cannot_take(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('products/sample.png', 'raw-sample-bytes');

        $fakePngBase64 = base64_encode("\x89PNG\r\n\x1a\n");
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'predictions' => [
                    ['bytesBase64Encoded' => $fakePngBase64],
                ],
            ], 200),
        ]);

        $provider = new GoogleImagenProvider('fake-key');
        $provider->generate(new GenerationInput(
            type: 'image',
            prompt: 'luxury perfume',
            aspectRatio: '9:16',
            assetDisk: 's3',
            assetPath: 'products/sample.png'
        ));

        // `predict` documents a prompt and nothing else. The photo on disk
        // must stay out of this request - a field the endpoint does not
        // document is a 400 waiting to end the whole chain - which is why a
        // reference-bearing image is routed to GoogleImageEditProvider.
        Http::assertSent(function ($request) {
            return ! isset($request['instances'][0]['referenceImage'])
                && $request['instances'][0]['prompt'] === 'luxury perfume';
        });
    }

    public function test_it_handles_rate_limit_429_error(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Resource exhausted'], 429),
        ]);

        $provider = new GoogleImagenProvider('fake-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('محدودیت نرخ درخواست هوش مصنوعی گوگل (429) فرا رسیده است.');

        $provider->generate(new GenerationInput('image', 'luxury perfume', '1:1'));
    }

    public function test_it_handles_server_error_500(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Internal error'], 500),
        ]);

        $provider = new GoogleImagenProvider('fake-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('خطا در پاسخ هوش مصنوعی گوگل: 500');

        $provider->generate(new GenerationInput('image', 'luxury perfume', '1:1'));
    }

    public function test_rate_limit_error_is_marked_retryable_for_fallback(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Resource exhausted'], 429),
        ]);

        $provider = new GoogleImagenProvider('fake-key');

        try {
            $provider->generate(new GenerationInput('image', 'luxury perfume', '1:1'));
            $this->fail('Expected a rate limit exception.');
        } catch (AiProviderException $exception) {
            $this->assertTrue($exception->retryable());
        }
    }

    public function test_server_error_is_marked_retryable_for_fallback(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Internal error'], 503),
        ]);

        $provider = new GoogleImagenProvider('fake-key');

        try {
            $provider->generate(new GenerationInput('image', 'luxury perfume', '1:1'));
            $this->fail('Expected a server error exception.');
        } catch (AiProviderException $exception) {
            $this->assertTrue($exception->retryable());
        }
    }

    public function test_client_validation_error_is_marked_permanent(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Bad request'], 400),
        ]);

        $provider = new GoogleImagenProvider('fake-key');

        try {
            $provider->generate(new GenerationInput('image', 'luxury perfume', '1:1'));
            $this->fail('Expected a validation exception.');
        } catch (AiProviderException $exception) {
            $this->assertFalse($exception->retryable());
            $this->assertSame('خطا در پاسخ هوش مصنوعی گوگل: 400', $exception->getMessage());
        }
    }

    public function test_missing_api_key_is_marked_retryable_so_the_chain_can_fall_back(): void
    {
        $provider = new GoogleImagenProvider('');

        try {
            $provider->generate(new GenerationInput('image', 'luxury perfume', '1:1'));
            $this->fail('Expected a missing key exception.');
        } catch (AiProviderException $exception) {
            $this->assertTrue($exception->retryable());
        }
    }
}
