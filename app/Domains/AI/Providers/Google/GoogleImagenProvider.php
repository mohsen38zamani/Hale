<?php

namespace App\Domains\AI\Providers\Google;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;

class GoogleImagenProvider implements GenerationProvider
{
    private string $apiKey;

    private string $baseUrl;

    private string $model;

    private int $timeout;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $model = null,
        ?int $timeout = null,
    ) {
        $this->apiKey = $apiKey ?? (string) config('ai.providers.google.api_key', '');
        $this->baseUrl = $baseUrl ?? (string) config('ai.providers.google.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->model = $model ?? (string) config('ai.providers.google.imagen_model', 'imagen-3.0-generate-002');
        $this->timeout = $timeout ?? (int) config('ai.providers.google.timeout', 60);
    }

    public function key(): string
    {
        return 'google_imagen';
    }

    public function supports(GenerationInput $input): bool
    {
        return $input->type === 'image';
    }

    public function generate(GenerationInput $input): GenerationResult
    {
        if (empty($this->apiKey)) {
            // Treated as transient so the fallback chain can reach the next
            // configured provider (e.g. the local fake one) without config.
            throw new AiProviderException('Google AI API Key تنظیم نشده است.');
        }

        // `predict` takes a prompt and nothing else - this endpoint has no
        // image input, which is exactly why a request carrying a product
        // photo is answered by GoogleImageEditProvider instead of here.
        $instances = [
            ['prompt' => $input->prompt],
        ];

        $url = rtrim($this->baseUrl, '/')."/models/{$this->model}:predict?key={$this->apiKey}";

        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'instances' => $instances,
                'parameters' => [
                    'sampleCount' => 1,
                    'aspectRatio' => $input->aspectRatio,
                    'outputMimeType' => 'image/png',
                ],
            ]);

        if ($response->status() === 429) {
            throw new AiProviderException('محدودیت نرخ درخواست هوش مصنوعی گوگل (429) فرا رسیده است.');
        }

        if (! $response->successful()) {
            $status = $response->status();
            // 5xx/408 are transient; other 4xx responses are request errors
            // that would fail identically on the next provider.
            throw new AiProviderException("خطا در پاسخ هوش مصنوعی گوگل: {$status}", $status >= 500 || $status === 408);
        }

        $data = $response->json();
        $base64 = $data['predictions'][0]['bytesBase64Encoded']
            ?? $data['candidates'][0]['content']['parts'][0]['inlineData']['data']
            ?? null;

        if (! $base64) {
            throw new AiProviderException('خروجی تصویر معتبری از گوگل دریافت نشد.');
        }

        $contents = base64_decode($base64);
        if ($contents === false) {
            throw new AiProviderException('رمزگشایی تصویر خروجی گوگل ناموفق بود.');
        }

        return new GenerationResult(
            contents: $contents,
            mime: 'image/png',
            extension: 'png',
            model: $this->model,
            costUsd: 0.04,
            metadata: [
                'provider' => 'google_imagen',
                'aspect_ratio' => $input->aspectRatio,
            ]
        );
    }
}
