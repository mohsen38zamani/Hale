<?php

namespace Tests\Feature\AI;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

/**
 * `ai:verify` is the only thing in Hale that speaks to the real Google API,
 * so the honest way to test it is to answer with the shapes the discovery
 * document and the official SDK converters say the service uses. If these
 * fakes and the providers ever disagree, the command is what says so.
 */
class AiVerifyCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Redis::connection('default')->flushdb();

        // The poll loop still runs; it just must not sleep inside the suite.
        config(['ai.providers.google.veo_poll_interval' => 0]);
    }

    public function test_it_refuses_to_pretend_anything_was_verified_without_a_key(): void
    {
        config(['ai.providers.google.api_key' => '']);

        $this->assertSame(1, Artisan::call('ai:verify'));
        $this->assertStringContainsString('GOOGLE_AI_API_KEY تنظیم نشده است', Artisan::output());
    }

    public function test_it_reports_every_capability_when_the_service_answers_as_documented(): void
    {
        config(['ai.providers.google.api_key' => 'fake-key']);
        $this->fakeGoogle();

        $this->assertSame(0, Artisan::call('ai:verify'));

        $output = Artisan::output();
        $this->assertStringContainsString('ترجمهٔ انگلیس: موفق', $output);
        $this->assertStringContainsString('تصویر (Imagen): موفق', $output);
        $this->assertStringContainsString('تصویر با عکسِ محصول (نانو بنان): موفق', $output);
        $this->assertStringContainsString('ویدئو با عکسِ محصول (Veo): موفق', $output);
        $this->assertStringContainsString('ویدئو با قابِ اول و آخر (Veo): موفق', $output);
        $this->assertStringContainsString('5 مسیر بررسی شد؛ 0 ناموفق', $output);
    }

    public function test_it_points_at_the_path_that_broke_when_one_capability_fails(): void
    {
        config(['ai.providers.google.api_key' => 'fake-key']);
        $this->fakeGoogle([
            '*:predictLongRunning*' => Http::response(['error' => 'model not found'], 404),
        ]);

        $this->assertSame(1, Artisan::call('ai:verify'));

        $output = Artisan::output();
        $this->assertStringContainsString('ویدئو با عکسِ محصول (Veo): ناموفق — خطا در پاسخ هوش مصنوعی گوگل: 404', $output);
        $this->assertStringContainsString('ویدئو با قابِ اول و آخر (Veo): ناموفق', $output);
        // Both frame fields are asked of the same broken endpoint, so the
        // diagnostic call runs too and is counted like the rest.
        $this->assertStringContainsString('ویدئو با قابِ اول به‌تنهایی (Veo): ناموفق', $output);
        $this->assertStringContainsString('6 مسیر بررسی شد؛ 3 ناموفق', $output);
        // A broken video must not cast doubt on the paths that answered.
        $this->assertStringContainsString('تصویر (Imagen): موفق', $output);
    }

    /**
     * The single call asks for image and lastFrame together, so a failure
     * blames both. This is what the second, cheaper call is for: when the
     * opening frame answers and the closing one does not, the report has
     * to say which of the two the service refused.
     */
    public function test_it_names_last_frame_as_the_field_that_was_refused(): void
    {
        config(['ai.providers.google.api_key' => 'fake-key']);
        $this->fakeGoogle(refuseLastFrame: true);

        $this->assertSame(1, Artisan::call('ai:verify'));

        $output = Artisan::output();
        $this->assertStringContainsString('ویدئو با قابِ اول و آخر (Veo): ناموفق', $output);
        $this->assertStringContainsString('ویدئو با قابِ اول به‌تنهایی (Veo): موفق', $output);
        // The reference path is untouched by the refusal and must still pass.
        $this->assertStringContainsString('ویدئو با عکسِ محصول (Veo): موفق', $output);
    }

    /** @param  array<string, Response>  $stubs */
    private function fakeGoogle(array $stubs = [], bool $refuseLastFrame = false): void
    {
        $png = base64_encode("\x89PNG\r\n\x1a\n");

        Http::fake($stubs + [
            // Ordered: `predictLongRunning` contains `predict`, so the video
            // has to be registered first or the wider pattern swallows it.
            // A refusal is answered per request rather than per pattern: the
            // same endpoint accepts an opening frame and turns away a
            // closing one, and finding that disagreement is the whole point
            // of the second call.
            '*:predictLongRunning*' => function ($request) use ($refuseLastFrame) {
                if ($refuseLastFrame && isset(json_decode((string) $request->body(), true)['instances'][0]['lastFrame'])) {
                    return Http::response(['error' => ['message' => 'lastFrame is not supported']], 400);
                }

                return Http::response([
                    'name' => 'models/veo-2.0-generate-001/operations/abc',
                    'done' => false,
                ], 200);
            },
            '*:generateContent*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [
                        ['text' => 'This is a short test.'],
                        ['inlineData' => ['mimeType' => 'image/png', 'data' => $png]],
                    ]],
                ]],
            ], 200),
            '*:predict*' => Http::response(['predictions' => [['bytesBase64Encoded' => $png]]], 200),
            '*/operations/*' => Http::response([
                'name' => 'models/veo-2.0-generate-001/operations/abc',
                'done' => true,
                'response' => [
                    'generateVideoResponse' => [
                        'generatedSamples' => [[
                            'video' => ['encodedVideo' => base64_encode("\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom")],
                        ]],
                    ],
                ],
            ], 200),
        ]);
    }
}
