<?php

namespace App\Domains\AI\Services;

use App\Domains\Generations\Models\Generation;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Social caption generator (Persian/English, tone-aware, with hashtags).
 *
 * The text model is called through the same Google credentials as the
 * image providers; whenever it is unconfigured, unreachable or returns
 * something unparseable the service falls back to a deterministic
 * template built from the product, goal and brand kit — mirroring the
 * image fallback philosophy so the feature never hard-fails.
 */
class CaptionService
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
        $this->model = $model ?? (string) config('ai.providers.google.text_model', 'gemini-2.0-flash');
        $this->timeout = $timeout ?? (int) config('ai.providers.google.timeout', 60);
    }

    /**
     * @return array{caption: string, hashtags: list<string>, source: 'ai'|'fallback'}
     */
    public function generate(Generation $generation, string $language, string $tone): array
    {
        $context = $this->context($generation, $language, $tone);

        $result = $this->askModel($context);
        if ($result === null) {
            $result = $this->fallback($context);
            $source = 'fallback';
        } else {
            $source = 'ai';
        }

        return [
            'caption' => $result['caption'],
            'hashtags' => $result['hashtags'],
            'source' => $source,
        ];
    }

    /**
     * @return array{language: string, tone: string, product: string, description: string, goal: string, brand: string, tagline: string, brand_tone: string}
     */
    private function context(Generation $generation, string $language, string $tone): array
    {
        $project = $generation->creativeProject;
        $product = $project?->product;
        $brand = $generation->user?->brandKit;

        return [
            'language' => $language,
            'tone' => $tone,
            'product' => trim((string) ($product?->name ?? '')),
            'description' => trim((string) ($product?->description ?? '')),
            'goal' => (string) ($project?->goal?->value ?? 'introduction'),
            'brand' => trim((string) ($brand?->name ?? '')),
            'tagline' => trim((string) ($brand?->tagline ?? '')),
            'brand_tone' => trim((string) ($brand?->tone ?? '')),
        ];
    }

    /**
     * @param  array<string, string>  $context
     * @return array{caption: string, hashtags: list<string>}|null
     */
    private function askModel(array $context): ?array
    {
        if ($this->apiKey === '') {
            return null;
        }

        $url = rtrim($this->baseUrl, '/')."/models/{$this->model}:generateContent?key={$this->apiKey}";

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $this->instruction($context)]]],
                    ],
                    'generationConfig' => [
                        'temperature' => (float) config('ai.text.temperature', 0.9),
                        'maxOutputTokens' => (int) config('ai.text.max_output_tokens', 540),
                        'responseMimeType' => 'application/json',
                    ],
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return $this->parseJson($response->json('candidates.0.content.parts.0.text'));
    }

    /**
     * @param  array<string, string>  $context
     */
    private function instruction(array $context): string
    {
        $product = $context['product'] !== '' ? $context['product'] : ($context['language'] === 'fa' ? 'محصول تازهٔ Hale' : "Hale's latest product");

        if ($context['language'] === 'fa') {
            $lines = [
                'برای یک پست شبکهٔ اجتماعی یک کپشن کوتاه و جذاب بنویس.',
                'زبان: فارسی',
                'لحن: '.$this->toneLabelFa($context['tone']),
                'محصول: '.$product,
                'توضیح محصول: '.($context['description'] !== '' ? $context['description'] : '-'),
                'هدف پست: '.$this->goalLabelFa($context['goal']),
            ];
            if ($context['brand'] !== '') {
                $lines[] = 'برند: '.$context['brand'].($context['tagline'] !== '' ? ' | شعار: '.$context['tagline'] : '');
            }
            if ($context['brand_tone'] !== '') {
                $lines[] = 'لحن برند: '.$context['brand_tone'];
            }
            $lines[] = 'حداکثر ۵ هشتگ مرتبط اضافه کن.';
            $lines[] = 'فقط و فقط یک JSON معتبر بدون هیچ متن یا کد اضافه برگردان با ساختار: {"caption": "متن کپشن", "hashtags": ["#نمونه"]}';

            return implode("\n", $lines);
        }

        $lines = [
            'Write a short, engaging social media caption.',
            'Language: English',
            'Tone: '.$context['tone'],
            'Product: '.$product,
            'Product description: '.($context['description'] !== '' ? $context['description'] : '-'),
            'Post goal: '.str_replace('_', ' ', $context['goal']),
        ];
        if ($context['brand'] !== '') {
            $lines[] = 'Brand: '.$context['brand'].($context['tagline'] !== '' ? ' | tagline: '.$context['tagline'] : '');
        }
        $lines[] = 'Add at most 5 relevant hashtags.';
        $lines[] = 'Reply with one valid JSON object only, no extra text or code fences: {"caption": "...", "hashtags": ["#example"]}';

        return implode("\n", $lines);
    }

    /**
     * @return array{caption: string, hashtags: list<string>}|null
     */
    private function parseJson(mixed $raw): ?array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $text = trim($raw);
        // Be forgiving about models that wrap JSON in code fences anyway.
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $text) ?? $text;
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded) || ! isset($decoded['caption'])) {
            return null;
        }

        $caption = trim((string) $decoded['caption']);
        if ($caption === '') {
            return null;
        }

        $hashtags = [];
        foreach ((array) ($decoded['hashtags'] ?? []) as $hashtag) {
            $tag = trim((string) $hashtag);
            if ($tag === '' || $tag === '#') {
                continue;
            }
            $hashtags[] = str_starts_with($tag, '#') ? $tag : '#'.$tag;
            if (count($hashtags) === 8) {
                break;
            }
        }

        return ['caption' => $caption, 'hashtags' => $hashtags];
    }

    /**
     * Deterministic template used whenever the text model cannot answer.
     *
     * @param  array<string, string>  $context
     * @return array{caption: string, hashtags: list<string>}
     */
    private function fallback(array $context): array
    {
        $product = $context['product'] !== '' ? $context['product'] : ($context['language'] === 'fa' ? 'محصول تازهٔ Hale' : "Hale's latest product");
        $brand = $context['brand'];
        $goal = $context['goal'];

        if ($context['language'] === 'fa') {
            $lead = $brand !== '' ? "«{$product}» از {$brand}" : "«{$product}»";
            $goalLine = self::GOAL_LINES_FA[$goal] ?? self::GOAL_LINES_FA['introduction'];
            $cta = self::CTA_FA[$context['tone']] ?? self::CTA_FA['friendly'];
            $caption = trim($lead.' '.$goalLine.($context['tagline'] !== '' ? ' '.$context['tagline'] : '').' '.$cta);

            $tags = array_merge(
                in_array($goal, ['sales', 'promotion'], true) ? ['#خرید_آنلاین', '#پیشنهاد_ویژه'] : ['#خرید_آنلاین'],
                array_slice(self::GOAL_TAGS_FA[$goal] ?? [], 0, 2),
                $brand !== '' ? ['#'.str_replace([' ', '‌'], '_', $brand)] : [],
            );

            return ['caption' => $caption, 'hashtags' => array_slice(array_values(array_unique($tags)), 0, 5)];
        }

        $lead = $brand !== '' ? "{$product} from {$brand}" : $product;
        $goalLine = self::GOAL_LINES_EN[$goal] ?? self::GOAL_LINES_EN['introduction'];
        $cta = self::CTA_EN[$context['tone']] ?? self::CTA_EN['friendly'];
        $caption = trim($lead.'. '.$goalLine.($context['tagline'] !== '' ? ' '.$context['tagline'].'.' : '').' '.$cta);

        $tags = array_merge(
            ['#shopping'],
            array_slice(self::GOAL_TAGS_EN[$goal] ?? [], 0, 2),
            $brand !== '' ? ['#'.preg_replace('/\s+/', '', $brand)] : [],
        );

        return ['caption' => $caption, 'hashtags' => array_slice(array_values(array_unique($tags)), 0, 5)];
    }

    private const GOAL_LINES_FA = [
        'introduction' => 'یه نگاه بنداز و ببین چطور می‌تونه کارت را راحت‌تر کنه.',
        'sales' => 'همین الان سفارش بده و تحویلش رو تجربه کن.',
        'branding' => 'یه انتخاب باکیفیت که با سلیقه‌ات جور درمیاد.',
        'promotion' => 'فرصت محدوده، از دستش نده.',
        'launch' => 'تازه رسیده و منتظر اولین نگاه توئه.',
        'engagement' => 'نظرت چیه؟ تو کامنت‌ها بگو.',
    ];

    private const CTA_FA = [
        'friendly' => 'همین الان امتحانش کن 🛍️',
        'formal' => 'برای اطلاعات بیشتر و خرید، از صفحهٔ محصول دیدن کنید.',
        'exciting' => 'همین الان بگیرش، موجودی محدوده! ⚡',
    ];

    private const GOAL_TAGS_FA = [
        'sales' => ['#فروش'],
        'promotion' => ['#تخفیف'],
        'launch' => ['#محصول_جدید'],
        'branding' => ['#برند'],
        'engagement' => ['#نظر_تو_چیه'],
        'introduction' => ['#معرفی_محصول'],
    ];

    private const GOAL_LINES_EN = [
        'introduction' => 'See how it can make your day easier.',
        'sales' => 'Order now and enjoy it today.',
        'branding' => 'A quality pick that matches your taste.',
        'promotion' => 'Limited time only — do not miss it.',
        'launch' => 'Just landed and waiting for your first look.',
        'engagement' => 'What do you think? Tell us in the comments.',
    ];

    private const CTA_EN = [
        'friendly' => 'Go grab yours 🛍️',
        'formal' => 'Visit the product page for details and ordering.',
        'exciting' => 'Get yours now — while stocks last! ⚡',
    ];

    private const GOAL_TAGS_EN = [
        'sales' => ['#sale'],
        'promotion' => ['#discount'],
        'launch' => ['#newarrival'],
        'branding' => ['#brand'],
        'engagement' => ['#tellus'],
        'introduction' => ['#meettheproduct'],
    ];

    private function toneLabelFa(string $tone): string
    {
        return match ($tone) {
            'formal' => 'رسمی',
            'exciting' => 'هیجان‌انگیز',
            default => 'صمیمی',
        };
    }

    private function goalLabelFa(string $goal): string
    {
        return match ($goal) {
            'sales' => 'افزایش فروش',
            'branding' => 'برندینگ',
            'promotion' => 'تخفیف و پیشنهاد ویژه',
            'launch' => 'عرضهٔ محصول جدید',
            'engagement' => 'جذب مخاطب',
            default => 'معرفی محصول',
        };
    }
}
