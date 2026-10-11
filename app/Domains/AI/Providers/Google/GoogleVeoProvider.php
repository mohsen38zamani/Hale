<?php

namespace App\Domains\AI\Providers\Google;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Exceptions\AiProviderException;
use finfo;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Veo renders on a long-running operation. The call that starts a video
 * answers with an operation name, and the video only arrives on the polls
 * that follow - so this provider is a start, a loop, and a parse, not one
 * request and its body.
 *
 * Everything below the prompt follows the discovery document of the
 * service itself (`generativelanguage:v1beta`), which lists
 * `models/{model}:predictLongRunning` and `models/{model}/operations/{id}`
 * and no `predictVideo` method at all, cross-checked against the request
 * and response converters both official SDKs ship for it.
 */
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
        $this->timeout = $timeout ?? (int) config('ai.providers.google.veo_timeout', config('ai.providers.google.timeout', 120));
    }

    public function key(): string
    {
        return 'google_veo';
    }

    public function supports(GenerationInput $input): bool
    {
        return $input->type === 'video' && in_array($input->durationSeconds, [5, 8, 10], true);
    }

    public function generate(GenerationInput $input): GenerationResult
    {
        if (empty($this->apiKey)) {
            // Treated as transient so the fallback chain can reach the next
            // configured provider (e.g. the local fake one) without config.
            throw new AiProviderException('Google AI API Key تنظیم نشده است.');
        }

        $duration = $input->durationSeconds ?? 5;

        $response = Http::timeout($this->timeout)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($this->url('models/'.$this->model.':predictLongRunning'), [
                'instances' => [$this->instance($input)],
                'parameters' => [
                    'durationSeconds' => $duration,
                    'aspectRatio' => $input->aspectRatio,
                    'sampleCount' => 1,
                ],
            ]);

        $this->assertUsable($response);

        $operation = $response->json();
        if (! is_array($operation)) {
            throw new AiProviderException('پاسخ هوش مصنوعی گوگل ساختار عملیات ندارد.');
        }

        $operation = $this->await($operation);

        return new GenerationResult(
            contents: $this->contents($operation),
            mime: 'video/mp4',
            extension: 'mp4',
            model: $this->model,
            costUsd: $duration * (float) config('ai.pricing.default.video_per_second', 0.05),
            metadata: [
                'provider' => 'google_veo',
                'duration_seconds' => $duration,
                'aspect_ratio' => $input->aspectRatio,
            ],
        );
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.$path.'?key='.$this->apiKey;
    }

    /**
     * The prompt is the whole instance Hale needs. The product photo rides
     * along as an ASSET reference - the field the service documents for
     * "this object belongs in the video" - which is a different promise
     * than a first frame: the product is referenced throughout rather than
     * merely being where the clip starts.
     *
     * @return array<string, mixed>
     */
    private function instance(GenerationInput $input): array
    {
        $instance = ['prompt' => $input->prompt];

        $reference = $this->referenceImage($input);
        if ($reference !== null) {
            $instance['referenceImages'] = [$reference];
        }

        return $instance;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function referenceImage(GenerationInput $input): ?array
    {
        if (! $input->assetDisk || ! $input->assetPath) {
            return null;
        }
        if (! Storage::disk($input->assetDisk)->exists($input->assetPath)) {
            return null;
        }

        $raw = (string) Storage::disk($input->assetDisk)->get($input->assetPath);
        $limit = (int) config('ai.providers.google.veo_max_reference_bytes', 5 * 1024 * 1024);
        if ($raw === '' || strlen($raw) > $limit) {
            return null;
        }

        return [
            'image' => [
                'mimeType' => $this->mimeType($raw),
                'bytesBase64Encoded' => base64_encode($raw),
            ],
            // ASSET places the thing itself in the scene. STYLE would only
            // borrow its colours and light.
            'referenceType' => 'ASSET',
        ];
    }

    private function mimeType(string $raw): string
    {
        $detected = (new finfo(FILEINFO_MIME_TYPE))->buffer($raw);

        return is_string($detected) && $detected !== '' ? $detected : 'image/jpeg';
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function await(array $operation): array
    {
        $budget = (int) config('ai.providers.google.veo_operation_timeout', 300);
        $interval = max(0, (int) config('ai.providers.google.veo_poll_interval', 5));
        $deadline = microtime(true) + $budget;

        while (! ($operation['done'] ?? false)) {
            $name = (string) ($operation['name'] ?? '');
            if ($name === '' || microtime(true) >= $deadline) {
                // Giving up here is not a failure of this provider alone:
                // the chain still has somewhere to go.
                throw new AiProviderException('ویدیوی گوگل در مهلت تعیین‌شده آماده نشد.', true);
            }

            sleep($interval);

            $poll = Http::timeout($this->timeout)->get($this->url($name));
            $this->assertUsable($poll);

            $operation = $poll->json();
            if (! is_array($operation)) {
                throw new AiProviderException('پاسخ هوش مصنوعی گوگل ساختار عملیات ندارد.');
            }
        }

        return $operation;
    }

    private function assertUsable(HttpResponse $response): void
    {
        if ($response->status() === 429) {
            throw new AiProviderException('محدودیت نرخ درخواست هوش مصنوعی گوگل (429) فرا رسیده است.');
        }

        if (! $response->successful()) {
            $status = $response->status();
            // 5xx/408 are transient; other 4xx responses are request errors
            // that would fail identically on the next provider.
            throw new AiProviderException("خطا در پاسخ هوش مصنوعی گوگل: {$status}", $status >= 500 || $status === 408);
        }
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function contents(array $operation): string
    {
        $error = $operation['error'] ?? null;
        if (is_array($error)) {
            $code = (int) ($error['code'] ?? 0);
            $message = (string) ($error['message'] ?? 'خطای ناشناخته');

            throw new AiProviderException("ویدیوی گوگل ناتمام ماند: {$message}", $code >= 500 || $code === 408 || $code === 429);
        }

        $video = $operation['response']['generateVideoResponse']['generatedSamples'][0]['video'] ?? null;

        $contents = false;
        if (is_array($video) && is_string($video['encodedVideo'] ?? null) && $video['encodedVideo'] !== '') {
            $contents = base64_decode($video['encodedVideo'], true);
        } elseif (is_array($video) && is_string($video['uri'] ?? null) && $video['uri'] !== '') {
            $download = Http::timeout($this->timeout)->get($video['uri']);
            if (! $download->successful()) {
                throw new AiProviderException('دانلود ویدئوی خروجی از گوگل ناموفق بود.');
            }
            $contents = $download->body();
        }

        if (! is_string($contents)) {
            $response = $operation['response']['generateVideoResponse'] ?? null;
            $filtered = is_array($response) ? (int) ($response['raiMediaFilteredCount'] ?? 0) : 0;

            // A refusal is permanent - the same prompt would be refused the
            // same way on every retry - while an empty success is not.
            throw new AiProviderException($this->filteredMessage($operation), $filtered < 1);
        }

        if (strlen($contents) < 12 || substr($contents, 4, 4) !== 'ftyp') {
            throw new AiProviderException('محتوای خروجی ویدئو MP4 معتبر نیست.');
        }

        return $contents;
    }

    /**
     * A clip can finish cleanly and still be nothing: the safety layer
     * empties the operation rather than failing it, so "no video" is its
     * own message rather than a shrug - and it is a permanent one, since
     * the same prompt would be refused the same way on every retry.
     *
     * @param  array<string, mixed>  $operation
     */
    private function filteredMessage(array $operation): string
    {
        $response = $operation['response']['generateVideoResponse'] ?? null;
        $filtered = is_array($response) ? (int) ($response['raiMediaFilteredCount'] ?? 0) : 0;

        if ($filtered < 1) {
            return 'خروجی ویدئوی معتبری از گوگل دریافت نشد.';
        }

        $reasons = is_array($response) && is_array($response['raiMediaFilteredReasons'] ?? null) ? $response['raiMediaFilteredReasons'] : [];

        return 'ویدیوی گوگل بر اساس سیاست‌های محتوایی تولید نشد'.($reasons === [] ? '.' : ': '.implode('، ', $reasons));
    }
}
