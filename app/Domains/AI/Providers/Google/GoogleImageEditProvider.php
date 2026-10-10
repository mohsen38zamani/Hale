<?php

namespace App\Domains\AI\Providers\Google;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Image generation and editing through Google's Gemini image model (nano
 * banana family).
 *
 * Unlike Imagen generation this goes through generateContent with the
 * source photo inlined next to the prompt, which is what makes background
 * removal, outpainting and shadow placement possible - and what makes it
 * the right answer for a creative image that arrives with the product's own
 * photo, because `predict` has no image input at all. The target framing
 * travels in the prompt text (`Square 1:1 frame.`), so no model-specific
 * imageConfig is required.
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

    public function supports(GenerationInput $input): bool
    {
        if ($input->type === 'image_edit') {
            return true;
        }

        // A creative image that arrives with the product's own photo is
        // answered here too: nano banana is the Google model whose API takes
        // an image, so it is the only one that can keep the packaging, the
        // shape and the logo the prompt can only describe.
        return $input->type === 'image' && $this->hasReference($input);
    }

    /**
     * Only a photo that exists may claim the request: routing a dead path
     * here would fail an image that Imagen could still have answered from
     * the prompt alone.
     */
    private function hasReference(GenerationInput $input): bool
    {
        if ($input->assetDisk === null || $input->assetPath === null) {
            return false;
        }

        return Storage::disk($input->assetDisk)->exists($input->assetPath);
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
        $sourceMime = is_array($sizeInfo) && ! empty($sizeInfo['mime']) ? (string) $sizeInfo['mime'] : 'image/png';

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
            // The reservation was made against the price of the request's own
            // type, so settling with anything else would drift from it.
            costUsd: (float) config('ai.pricing.default.'.($input->type === 'image' ? 'image' : 'edit'), 0.04),
            inputTokens: (int) ($usage['promptTokenCount'] ?? 0),
            outputTokens: (int) ($usage['candidatesTokenCount'] ?? 0),
            metadata: [
                'provider' => 'google_image_edit',
                'aspect_ratio' => $input->aspectRatio,
            ]
        );
    }
}
