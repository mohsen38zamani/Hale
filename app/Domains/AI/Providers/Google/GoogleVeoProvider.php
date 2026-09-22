<?php

namespace App\Domains\AI\Providers\Google;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GoogleVeoProvider implements GenerationProvider
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
        $this->model = $model ?? (string) config('ai.providers.google.veo_model', 'veo-2.0-generate-001');
        $this->timeout = $timeout ?? (int) config('ai.providers.google.timeout', 120);
    }

    public function key(): string
    {
        return 'google_veo';
    }

    public function supports(string $type, ?int $durationSeconds = null): bool
    {
        return $type === 'video' && in_array($durationSeconds, [5, 8, 10], true);
    }

    public function generate(GenerationInput $input): GenerationResult
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('Google AI API Key تنظیم نشده است.');
        }

        $duration = $input->durationSeconds ?? 5;

        $instances = [
            [
                'prompt' => $input->prompt,
                'durationSeconds' => $duration,
                'aspectRatio' => $input->aspectRatio,
            ],
        ];

        if ($input->assetDisk && $input->assetPath && Storage::disk($input->assetDisk)->exists($input->assetPath)) {
            $rawImage = Storage::disk($input->assetDisk)->get($input->assetPath);
            $instances[0]['referenceImage'] = [
                'bytesBase64Encoded' => base64_encode($rawImage),
            ];
        }

        $url = rtrim($this->baseUrl, '/')."/models/{$this->model}:predictVideo?key={$this->apiKey}";

        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'instances' => $instances,
                'parameters' => [
                    'durationSeconds' => $duration,
                    'aspectRatio' => $input->aspectRatio,
                ],
            ]);

        if ($response->status() === 429) {
            throw new RuntimeException('محدودیت نرخ درخواست هوش مصنوعی گوگل (429) فرا رسیده است.');
        }

        if (! $response->successful()) {
            throw new RuntimeException("خطا در پاسخ هوش مصنوعی گوگل: {$response->status()}");
        }

        $data = $response->json();
        $base64 = $data['predictions'][0]['bytesBase64Encoded']
            ?? $data['predictions'][0]['video']['bytesBase64Encoded']
            ?? null;

        if (! $base64) {
            // Check if there is a download URI
            $downloadUri = $data['predictions'][0]['videoUri'] ?? null;
            if ($downloadUri) {
                $videoResponse = Http::timeout($this->timeout)->get($downloadUri);
                if (! $videoResponse->successful()) {
                    throw new RuntimeException('دانلود ویدئوی خروجی از گوگل ناموفق بود.');
                }
                $contents = $videoResponse->body();
            } else {
                throw new RuntimeException('خروجی ویدئوی معتبری از گوگل دریافت نشد.');
            }
        } else {
            $contents = base64_decode($base64);
        }

        if ($contents === false || strlen($contents) < 12 || substr($contents, 4, 4) !== 'ftyp') {
            throw new RuntimeException('محتوای خروجی ویدئو MP4 معتبر نیست.');
        }

        return new GenerationResult(
            contents: $contents,
            mime: 'video/mp4',
            extension: 'mp4',
            model: $this->model,
            costUsd: $duration * 0.05,
            metadata: [
                'provider' => 'google_veo',
                'duration_seconds' => $duration,
                'aspect_ratio' => $input->aspectRatio,
            ]
        );
    }
}
