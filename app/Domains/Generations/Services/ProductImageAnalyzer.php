<?php

declare(strict_types=1);

namespace App\Domains\Generations\Services;

use App\Domains\Creative\Enums\ProductCategory;
use App\Domains\Generations\Contracts\ProductImageAnalyzerInterface;
use App\Domains\Generations\Jobs\AnalyzeProductTrade;
use App\Domains\Media\Models\MediaAsset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * تحلیل دورگهٔ تصویر محصول (بند ۱۲ — آیتم ۱).
 *
 * لایهٔ ۱: آنالیز محلی (ابعاد، نسبت کادر، آلفا، جعبهٔ امن) — همیشه، بدون شبکه.
 * لایهٔ ۲: مدل بینایی در صف — صنف پیشنهادی + سبک + رنگ غالب؛ نتیجه در cache.
 */
class ProductImageAnalyzer implements ProductImageAnalyzerInterface
{
    /** پیشوند کلید cache برای پیشنهاد دورگه. */
    public const CACHE_PREFIX = 'hale.product-trade-analysis.';

    /** عمر پیشنهاد دورگه (۷ روز). */
    public const CACHE_TTL = 60 * 60 * 24 * 7;

    /** حداقل عرض/ارتفاع برای تحلیل. */
    public const MIN_SIDE = 256;

    public function analyzeLocally(MediaAsset $asset): array
    {
        $binary = $this->readBinary($asset);

        if ($binary === null) {
            return $this->emptyLocal();
        }

        $info = @getimagesizefromstring($binary);

        if ($info === false) {
            return $this->emptyLocal();
        }

        [$width, $height] = [$info[0], $info[1]];
        $mime = (string) ($info['mime'] ?? 'application/octet-stream');

        $paddingX = (int) round($width * 0.1);
        $paddingY = (int) round($height * 0.1);

        return [
            'width' => $width,
            'height' => $height,
            'ratio' => $height > 0 ? round($width / $height, 4) : 0.0,
            'has_alpha' => $this->detectAlpha($binary, $mime),
            'safe_zone' => [
                'left' => $paddingX,
                'top' => $paddingY,
                'right' => $width - $paddingX,
                'bottom' => $height - $paddingY,
            ],
            'analyzable' => $width >= self::MIN_SIDE && $height >= self::MIN_SIDE,
        ];
    }

    public function suggestion(MediaAsset $asset): array
    {
        $cached = Cache::get(self::CACHE_PREFIX.$asset->id);

        if (is_array($cached)) {
            return ['status' => 'ready', 'suggestion' => $cached];
        }

        if (! $this->analyzeLocally($asset)['analyzable']) {
            return ['status' => 'none', 'suggestion' => null];
        }

        // لایهٔ ۲ فقط در صف اجرا می‌شود؛ پاسخ API هرگز منتظر شبکه نمی‌ماند.
        AnalyzeProductTrade::dispatch((int) $asset->id);

        return ['status' => 'pending', 'suggestion' => null];
    }

    public function analyzeWithVision(MediaAsset $asset): void
    {
        $binary = $this->readBinary($asset);

        if ($binary === null) {
            return;
        }

        $apiKey = (string) config('ai.providers.google.api_key');

        if ($apiKey === '') {
            return;
        }

        $model = (string) config('ai.providers.google.vision_model', 'gemini-2.5-flash');
        $baseUrl = rtrim((string) config('ai.providers.google.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $url = sprintf('%s/models/%s:generateContent?key=%s', $baseUrl, $model, $apiKey);

        $prompt = <<<'TEXT'
Analyze this product photo. Respond ONLY with JSON:
{"category":"one of jewelry|watches|shoes|perfumes|cosmetics|fashion|bags|electronics|home_decor|general",
"confidence":0.0-1.0,"style_hint":"short style in English","key_color":"#RRGGBB"}
TEXT;

        try {
            $response = Http::timeout(20)->post($url, [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $this->mimeOf($binary), 'data' => base64_encode($binary)]],
                    ],
                ]],
            ])->json();
        } catch (\Throwable $e) {
            Log::info('trade-analysis-vision-failed', ['asset' => $asset->id, 'error' => $e->getMessage()]);

            return;
        }

        $text = (string) ($response['candidates'][0]['content']['parts'][0]['text'] ?? '');
        $parsed = $this->parseJson($text);

        if ($parsed === null) {
            return;
        }

        $category = ProductCategory::tryFrom((string) ($parsed['category'] ?? ''))
            ?? ProductCategory::General;

        $result = [
            'category' => $category->value,
            'confidence' => max(0.0, min(1.0, (float) ($parsed['confidence'] ?? 0.5))),
            'style_hint' => isset($parsed['style_hint']) ? Str::limit((string) $parsed['style_hint'], 80) : null,
            'key_color' => $this->normalizeColor($parsed['key_color'] ?? null),
        ];

        Cache::put(self::CACHE_PREFIX.$asset->id, $result, self::CACHE_TTL);
    }

    /**
     * @return array{width:int,height:int,ratio:float,has_alpha:bool,safe_zone:array{left:int,top:int,right:int,bottom:int},analyzable:bool}
     */
    private function emptyLocal(): array
    {
        return [
            'width' => 0,
            'height' => 0,
            'ratio' => 0.0,
            'has_alpha' => false,
            'safe_zone' => ['left' => 0, 'top' => 0, 'right' => 0, 'bottom' => 0],
            'analyzable' => false,
        ];
    }

    private function readBinary(MediaAsset $asset): ?string
    {
        try {
            $contents = Storage::disk((string) $asset->disk)->get((string) $asset->path);
        } catch (\Throwable) {
            return null;
        }

        return $contents === null ? null : (string) $contents;
    }

    private function mimeOf(string $binary): string
    {
        $info = @getimagesizefromstring($binary);

        return is_array($info) && isset($info['mime']) ? (string) $info['mime'] : 'image/png';
    }

    private function detectAlpha(string $binary, string $mime): bool
    {
        if (! in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
            return false;
        }

        $image = @imagecreatefromstring($binary);

        if ($image === false) {
            // بدون GD یا decode ناموفق: همین که فرمت شفاف‌پذیر باشد کافی است.
            return true;
        }

        $hasAlpha = false;
        $width = imagesx($image);
        $height = imagesy($image);

        foreach ([[0, 0], [$width - 1, 0], [0, $height - 1], [$width - 1, $height - 1]] as [$x, $y]) {
            $rgba = imagecolorat($image, $x, $y);
            if ((($rgba >> 24) & 0x7F) > 0) {
                $hasAlpha = true;
                break;
            }
        }

        imagedestroy($image);

        return $hasAlpha;
    }

    /**
     * فقط یک هگز واقعی قبول است؛ هر چیز دیگری دور ریخته می‌شود.
     */
    private function normalizeColor(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1 ? strtoupper($value) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJson(string $text): ?array
    {
        $clean = trim(preg_replace('/```(?:json)?/i', '', $text) ?? '');
        $decoded = json_decode($clean, true);

        return is_array($decoded) ? $decoded : null;
    }
}
