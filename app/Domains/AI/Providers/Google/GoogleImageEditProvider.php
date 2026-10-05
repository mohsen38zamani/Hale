<?php

namespace App\Domains\AI\Providers\Google;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Image editing through Google's Gemini image model (nano banana family).
 *
 * Unlike Imagen generation this goes through generateContent with the
 * source photo inlined next to an instruction prompt, which is what makes
 * background removal, outpainting and shadow placement possible. The target
 * framing (aspect ratio for expand) lives inside the prompt text, so no
 * model-specific imageConfig is required.
 */
class GoogleImageEditProvider implements GenerationProvider
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
        $this->model = $model ?? (string) config('ai.providers.google.edit_model', 'gemini-2.5-flash-image');
        $this->timeout = $timeout ?? (int) config('ai.providers.google.timeout', 60);
    }

    public function key(): string
    {
        return 'google_image_edit';
    }

    public function supports(string $type, ?int $durationSeconds = null): bool
    {
        return $type === 'image_edit';
    }

    public function generate(GenerationInput $input): GenerationResult
    {
        if (empty($this->apiKey)) {
            // Treated as transient so the fallback chain can reach the next
            // configured provider (e.g. the local fake one) without config.
            throw new AiProviderException('Google AI API Key تنظیم نشده است.');
        }

        if ($input->assetDisk === null || $input->assetPath === null || ! Storage::disk($input->assetDisk)->exists($input->assetPath)) {
            // An edit without its source photo can never succeed, on any
            // provider: stop the chain instead of burning the budget.
            throw new AiProviderException('تصویر منبع برای ویرایش در دسترس نیست.', false);
        }

        $rawImage = Storage::disk($input->assetDisk)->get($input->assetPath);
        $sizeInfo = @getimagesizefromstring($rawImage);
        $sourceMime = is_array($sizeInfo) && ! empty($sizeInfo[2]) ? (string) $sizeInfo[2] : 'image/png';

        $url = rtrim($this->baseUrl, '/')."/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'contents' => [[
                    'parts' => [
                        ['inline_data' => ['mime_type' => $sourceMime, 'data' => base64_encode($rawImage)]],
                        ['text' => $input->prompt],
                    ],
                ]],
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

        $parts = $response->json('candidates.0.content.parts') ?? [];
        $imagePart = null;
        foreach ((array) $parts as $part) {
            if (! empty($part['inlineData']['data']) || ! empty($part['inline_data']['data'])) {
                $imagePart = $part;
                break;
            }
        }

        $base64 = $imagePart['inlineData']['data'] ?? $imagePart['inline_data']['data'] ?? null;
        if (! $base64) {
            // Text-only reply (refusal, safety): permanent for this chain.
            throw new AiProviderException('مدل ویرایش تصویر، خروجی تصویری تولید نکرد.', false);
        }

        $contents = base64_decode($base64);
        if ($contents === false) {
            throw new AiProviderException('رمزگشایی تصویر خروجی گوگل ناموفق بود.');
        }

        $mime = (string) ($imagePart['inlineData']['mimeType'] ?? $imagePart['inline_data']['mime_type'] ?? 'image/png');
        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        $usage = (array) $response->json('usageMetadata', []);

        return new GenerationResult(
            contents: $contents,
            mime: $mime,
            extension: $extension,
            model: $this->model,
            costUsd: (float) config('ai.pricing.default.edit', 0.04),
            inputTokens: (int) ($usage['promptTokenCount'] ?? 0),
            outputTokens: (int) ($usage['candidatesTokenCount'] ?? 0),
            metadata: [
                'provider' => 'google_image_edit',
                'aspect_ratio' => $input->aspectRatio,
            ]
        );
    }
}
