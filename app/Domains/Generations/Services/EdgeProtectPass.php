<?php

declare(strict_types=1);

namespace App\Domains\Generations\Services;

use App\Domains\Creative\Contracts\CategoryPromptDecoratorInterface;
use App\Domains\Creative\Prompts\CategoryPromptBuilder;
use App\Domains\Generations\Contracts\EdgeProtectPassInterface;
use App\Domains\Media\Models\MediaAsset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * The edge-protection pass of foreground-invariant inpainting: the scene
 * was rendered once, and this pass sends it back to the edit model with
 * the customer's own product photo next to it, so the product's pixels
 * are landed again instead of a model's paraphrase of them.
 *
 * Failures are answered with null by design: the pass only ever adds
 * quality on top of a finished render, it never decides one.
 */
class EdgeProtectPass implements EdgeProtectPassInterface
{
    public function __construct(
        protected readonly CategoryPromptDecoratorInterface $categories = new CategoryPromptBuilder,
    ) {}

    public function apply(string $sceneBytes, string $sceneMime, MediaAsset $productAsset, ?string $category): ?array
    {
        if (! (bool) config('ai.edge_protect.enabled')) {
            return null;
        }

        $apiKey = (string) config('ai.providers.google.api_key');
        if ($apiKey === '') {
            return null;
        }

        $productPhoto = $this->productPhoto($productAsset);
        if ($productPhoto === null) {
            return null;
        }

        $result = $this->callModel($sceneBytes, $productPhoto, $this->prompt($category));
        if ($result === null) {
            return null;
        }

        // The generation's output contract already accepted `$sceneMime`;
        // the pass must answer in the same format or the contract turns a
        // better picture into a failed generation.
        $contents = $this->adoptMime($result['contents'], $sceneMime);
        if ($contents === null) {
            return null;
        }

        return [
            'contents' => $contents,
            'mime' => $sceneMime,
            'model' => $result['model'],
            'costUsd' => (float) config('ai.edge_protect.cost_usd'),
            'inputTokens' => $result['inputTokens'],
            'outputTokens' => $result['outputTokens'],
        ];
    }

    /**
     * @return array{contents:string,model:string,inputTokens:int,outputTokens:int}|null
     */
    private function callModel(string $sceneBytes, string $productPhoto, string $prompt): ?array
    {
        $model = (string) config('ai.providers.google.edit_model', 'gemini-2.5-flash-image');
        $url = sprintf(
            '%s/models/%s:generateContent?key=%s',
            rtrim((string) config('ai.providers.google.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
            $model,
            (string) config('ai.providers.google.api_key')
        );

        try {
            $response = Http::timeout((int) config('ai.edge_protect.timeout', 60))->post($url, [
                'contents' => [[
                    'parts' => [
                        ['inline_data' => ['mime_type' => $this->mimeOf($sceneBytes), 'data' => base64_encode($sceneBytes)]],
                        ['inline_data' => ['mime_type' => $this->mimeOf($productPhoto), 'data' => base64_encode($productPhoto)]],
                        ['text' => $prompt],
                    ],
                ]],
            ]);
        } catch (\Throwable $requestException) {
            Log::info('edge-protect-pass-request-failed', ['error' => $requestException->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::info('edge-protect-pass-refused', ['status' => $response->status()]);

            return null;
        }

        $imagePart = collect((array) $response->json('candidates.0.content.parts', []))
            ->first(fn (mixed $part): bool => is_array($part)
                && (! empty($part['inlineData']['data']) || ! empty($part['inline_data']['data'])));
        if ($imagePart === null) {
            // A text-only reply (refusal, safety): the render stands as it is.
            Log::info('edge-protect-pass-had-no-image', []);

            return null;
        }

        $contents = base64_decode(
            (string) ($imagePart['inlineData']['data'] ?? $imagePart['inline_data']['data']),
            true
        );
        if ($contents === false) {
            Log::info('edge-protect-pass-undecodable', []);

            return null;
        }

        $usage = (array) $response->json('usageMetadata', []);

        return [
            'contents' => $contents,
            'model' => $model,
            'inputTokens' => (int) ($usage['promptTokenCount'] ?? 0),
            'outputTokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
        ];
    }

    /**
     * On purpose word-for-word the compiled prompt's companion: the scale
     * sentence and the guardrail come from the trade catalogue, so the
     * pass promises the same protection the prompt did.
     */
    private function prompt(?string $category): string
    {
        $prompt = strtr((string) config('creative.edge_protect_prompt'), [
            '{scale}' => trim($this->categories->scale($category)),
            '{guardrail}' => trim($this->categories->guardrail($category)),
        ]);

        // With no trade both clauses are empty lines; the model sees the
        // same spelled-out promise, without the leftover breathing room.
        return trim((string) preg_replace("/\n{2,}/", "\n", $prompt));
    }

    private function adoptMime(string $bytes, string $mime): ?string
    {
        if ($this->mimeOf($bytes) === $mime) {
            return $bytes;
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }

        if ($mime === 'image/jpeg') {
            $width = imagesx($image);
            $height = imagesy($image);
            $flat = imagecreatetruecolor($width, $height);
            imagefilledrectangle($flat, 0, 0, $width, $height, imagecolorallocate($flat, 255, 255, 255));
            imagealphablending($flat, true);
            imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);
            imagedestroy($image);
            $image = $flat;
        }

        ob_start();
        $encoded = match ($mime) {
            'image/jpeg' => imagejpeg($image, null, 92),
            'image/webp' => function_exists('imagewebp') ? imagewebp($image, null, 92) : false,
            default => imagepng($image, null, 6),
        };
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        if ($encoded !== true || $contents === '') {
            return null;
        }

        return $contents;
    }

    private function mimeOf(string $bytes): string
    {
        $info = @getimagesizefromstring($bytes);

        return is_array($info) ? (string) ($info['mime'] ?? 'image/png') : 'image/png';
    }

    private function productPhoto(MediaAsset $productAsset): ?string
    {
        try {
            $contents = Storage::disk((string) $productAsset->disk)->get((string) $productAsset->path);
        } catch (\Throwable) {
            return null;
        }

        return $contents === null ? null : (string) $contents;
    }
}
