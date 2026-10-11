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
        $this->assertStringContainsString('4 مسیر بررسی شد؛ 0 ناموفق', $output);
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
        $this->assertStringContainsString('4 مسیر بررسی شد؛ 1 ناموفق', $output);
        // A broken video must not cast doubt on the paths that answered.
        $this->assertStringContainsString('تصویر (Imagen): موفق', $output);
    }

    /** @param  array<string, Response>  $stubs */
    private function fakeGoogle(array $stubs = []): void
    {
        $png = base64_encode("\x89PNG\r\n\x1a\n");

        Http::fake($stubs + [
            // Ordered: `predictLongRunning` contains `predict`, so the video
            // has to be registered first or the wider pattern swallows it.
            '*:predictLongRunning*' => Http::response([
                'name' => 'models/veo-2.0-generate-001/operations/abc',
                'done' => false,
            ], 200),
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
