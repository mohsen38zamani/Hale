<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Exceptions\AiProviderException;
use App\Domains\AI\Providers\Google\GoogleVeoProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class GoogleVeoProviderTest extends TestCase
{
    /** Structurally an MP4: size, then the ftyp box. */
    private const MP4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        // The loop must still run; it just must not sleep inside the suite.
        config(['ai.providers.google.veo_poll_interval' => 0]);
    }

    public function test_it_supports_video_with_specific_durations_only(): void
    {
        $provider = new GoogleVeoProvider('fake-key');

        $this->assertTrue($provider->supports(new GenerationInput('video', 'p', '9:16', 5)));
        $this->assertTrue($provider->supports(new GenerationInput('video', 'p', '9:16', 8)));
        $this->assertTrue($provider->supports(new GenerationInput('video', 'p', '9:16', 10)));
        $this->assertFalse($provider->supports(new GenerationInput('video', 'p', '9:16', 15)));
        $this->assertFalse($provider->supports(new GenerationInput('image', 'p', '1:1', 5)));
    }

    public function test_it_throws_when_api_key_is_missing(): void
    {
        $provider = new GoogleVeoProvider('');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google AI API Key تنظیم نشده است.');

        $provider->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
    }

    public function test_it_starts_the_operation_and_reads_the_video_out_of_it(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*predictLongRunning*' => Http::response($this->started(), 200),
            'https://generativelanguage.googleapis.com/*/operations/*' => Http::response($this->finished(base64_encode(self::MP4), null), 200),
        ]);

        $result = (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 8));

        $this->assertSame(self::MP4, $result->contents);
        $this->assertSame('video/mp4', $result->mime);
        $this->assertSame('mp4', $result->extension);
        $this->assertSame('veo-2.0-generate-001', $result->model);
        $this->assertSame(0.40, $result->costUsd); // 8 * 0.05
        $this->assertSame(8, $result->metadata['duration_seconds']);

        // The render is started on a long-running operation and polled on
        // the operation resource, not read back off the starting call.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/veo-2.0-generate-001:predictLongRunning')
                && $request['instances'] === [['prompt' => 'product showcase']]
                && $request['parameters'] === ['durationSeconds' => 8, 'aspectRatio' => '9:16', 'sampleCount' => 1];
        });
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), 'models/veo-2.0-generate-001/operations/abc'));
    }

    public function test_it_polls_until_the_operation_is_done(): void
    {
        $poll = 0;
        Http::fake([
            'https://generativelanguage.googleapis.com/*predictLongRunning*' => Http::response($this->started(), 200),
            'https://generativelanguage.googleapis.com/*/operations/*' => function () use (&$poll) {
                $poll++;

                return Http::response($poll < 3 ? $this->started() : $this->finished(base64_encode(self::MP4), null), 200);
            },
        ]);

        $result = (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));

        $this->assertSame(self::MP4, $result->contents);
        Http::assertSentCount(4); // the start, then three polls
    }

    public function test_it_sends_the_product_photo_as_an_asset_reference_when_hale_has_one(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('products/sample.png', base64_decode(self::PNG, true));
        $this->fakeVideoChain();

        (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', 5, 's3', 'products/sample.png'));

        // ASSET says "this object belongs in the video", which is what a
        // product shot is - and it is the field the service documents for
        // it, not a guess that could end the video path.
        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $reference = $request['instances'][0]['referenceImages'][0] ?? null;

            return $request['instances'][0]['prompt'] === 'product showcase'
                && $reference['referenceType'] === 'ASSET'
                && $reference['image']['mimeType'] === 'image/png'
                && base64_decode($reference['image']['bytesBase64Encoded'], true) === base64_decode(self::PNG, true);
        });
    }

    public function test_it_opens_the_clip_on_the_photo_when_the_first_frame_is_asked_for(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('products/sample.png', base64_decode(self::PNG, true));
        $this->fakeVideoChain();

        (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', 5, 's3', 'products/sample.png', null, 'first'));

        // instances[0].image is the opening frame - the clip starts on
        // exactly this picture rather than on whatever the model composes.
        // The reference is absent because the service refuses a request
        // that carries both: they are two promises about the same photo.
        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $instance = $request['instances'][0];

            return $instance['prompt'] === 'product showcase'
                && ! isset($instance['referenceImages'])
                && ! isset($instance['lastFrame'])
                && $instance['image']['mimeType'] === 'image/png'
                && base64_decode($instance['image']['bytesBase64Encoded'], true) === base64_decode(self::PNG, true);
        });
    }

    public function test_it_opens_and_closes_on_the_photo_when_both_frames_are_asked_for(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('products/sample.png', base64_decode(self::PNG, true));
        $this->fakeVideoChain();

        (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', 5, 's3', 'products/sample.png', null, 'first_last'));

        // lastFrame is documented as image-to-video only, so the opening
        // frame travels with it. Both carry the same bytes on purpose: a
        // clip that opens and closes on the product is the whole promise,
        // and the studio has one photo to give.
        Http::assertSent(function ($request) {
            if ($request->method() !== 'POST') {
                return false;
            }
            $instance = $request['instances'][0];

            return $instance['prompt'] === 'product showcase'
                && ! isset($instance['referenceImages'])
                && isset($instance['image'], $instance['lastFrame'])
                && $instance['lastFrame'] === $instance['image'];
        });
    }

    public function test_it_sends_only_the_prompt_when_hale_has_no_photo(): void
    {
        $this->fakeVideoChain();

        (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', 5));

        Http::assertSent(function ($request) {
            return $request->method() === 'POST' && $request['instances'] === [['prompt' => 'product showcase']];
        });
    }

    public function test_it_leaves_out_a_reference_photo_larger_than_the_inline_limit(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('products/sample.png', 'a photograph larger than the limit');
        config(['ai.providers.google.veo_max_reference_bytes' => 4]);
        $this->fakeVideoChain();

        (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', 5, 's3', 'products/sample.png'));

        // Sending it would be a permanent 400 and would take the render with
        // it; the prompt still describes the product without the photo.
        Http::assertSent(function ($request) {
            return $request->method() === 'POST' && $request['instances'] === [['prompt' => 'product showcase']];
        });
    }

    public function test_it_downloads_from_the_uri_when_the_video_is_not_inlined(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*predictLongRunning*' => Http::response($this->started(), 200),
            'https://generativelanguage.googleapis.com/*/operations/*' => Http::response($this->finished(null, 'https://storage.example/video.mp4'), 200),
            'https://storage.example/*' => Http::response(self::MP4, 200),
        ]);

        $result = (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));

        $this->assertSame(self::MP4, $result->contents);
    }

    public function test_it_reports_a_filtered_video_as_permanent(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*predictLongRunning*' => Http::response([
                'name' => $this->operation(),
                'done' => true,
                'response' => [
                    'generateVideoResponse' => [
                        'generatedSamples' => [],
                        'raiMediaFilteredCount' => 1,
                        'raiMediaFilteredReasons' => ['PERSONS'],
                    ],
                ],
            ], 200),
        ]);

        try {
            (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
            $this->fail('Expected a filtered video exception.');
        } catch (AiProviderException $exception) {
            // The safety layer empties the operation instead of failing it,
            // and the same prompt would be refused again and again.
            $this->assertFalse($exception->retryable());
            $this->assertSame('ویدیوی گوگل بر اساس سیاست‌های محتوایی تولید نشد: PERSONS', $exception->getMessage());
        }
    }

    public function test_a_client_error_on_the_operation_is_permanent(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*predictLongRunning*' => Http::response([
                'name' => $this->operation(),
                'done' => true,
                'error' => ['code' => 400, 'message' => 'Invalid duration'],
            ], 200),
        ]);

        try {
            (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
            $this->fail('Expected an operation error exception.');
        } catch (AiProviderException $exception) {
            $this->assertFalse($exception->retryable());
            $this->assertStringContainsString('Invalid duration', $exception->getMessage());
        }
    }

    public function test_a_server_error_on_the_operation_stays_retryable(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*predictLongRunning*' => Http::response([
                'name' => $this->operation(),
                'done' => true,
                'error' => ['code' => 503, 'message' => 'Backend unavailable'],
            ], 200),
        ]);

        try {
            (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
            $this->fail('Expected an operation error exception.');
        } catch (AiProviderException $exception) {
            $this->assertTrue($exception->retryable());
        }
    }

    public function test_it_gives_up_as_transient_when_the_operation_runs_out_of_time(): void
    {
        config(['ai.providers.google.veo_operation_timeout' => 0]);
        Http::fake([
            'https://generativelanguage.googleapis.com/*predictLongRunning*' => Http::response($this->started(), 200),
        ]);

        try {
            (new GoogleVeoProvider('fake-key'))->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
            $this->fail('Expected a timeout exception.');
        } catch (AiProviderException $exception) {
            // The chain still has somewhere to go, so giving up is not the
            // end of the generation.
            $this->assertTrue($exception->retryable());
            $this->assertSame('ویدیوی گوگل در مهلت تعیین‌شده آماده نشد.', $exception->getMessage());
        }
    }

    public function test_it_handles_rate_limit_429_error(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Resource exhausted'], 429),
        ]);

        $provider = new GoogleVeoProvider('fake-key');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('محدودیت نرخ درخواست هوش مصنوعی گوگل (429) فرا رسیده است.');

        $provider->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
    }

    public function test_rate_limit_error_is_marked_retryable_for_fallback(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Resource exhausted'], 429),
        ]);

        $provider = new GoogleVeoProvider('fake-key');

        try {
            $provider->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
            $this->fail('Expected a rate limit exception.');
        } catch (AiProviderException $exception) {
            $this->assertTrue($exception->retryable());
        }
    }

    public function test_client_validation_error_is_marked_permanent(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Bad request'], 400),
        ]);

        $provider = new GoogleVeoProvider('fake-key');

        try {
            $provider->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
            $this->fail('Expected a validation exception.');
        } catch (AiProviderException $exception) {
            $this->assertFalse($exception->retryable());
            $this->assertSame('خطا در پاسخ هوش مصنوعی گوگل: 400', $exception->getMessage());
        }
    }

    public function test_missing_api_key_is_marked_retryable_so_the_chain_can_fall_back(): void
    {
        $provider = new GoogleVeoProvider('');

        try {
            $provider->generate(new GenerationInput('video', 'product showcase', '9:16', durationSeconds: 5));
            $this->fail('Expected a missing key exception.');
        } catch (AiProviderException $exception) {
            $this->assertTrue($exception->retryable());
        }
    }

    private function fakeVideoChain(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*predictLongRunning*' => Http::response($this->started(), 200),
            'https://generativelanguage.googleapis.com/*/operations/*' => Http::response($this->finished(base64_encode(self::MP4), null), 200),
        ]);
    }

    private function operation(): string
    {
        return 'models/veo-2.0-generate-001/operations/abc';
    }

    /** @return array<string, mixed> */
    private function started(): array
    {
        return ['name' => $this->operation(), 'done' => false];
    }

    /** @return array<string, mixed> */
    private function finished(?string $encoded, ?string $uri = null): array
    {
        $video = ['encoding' => 'video/mp4'];

        if ($encoded !== null) {
            $video['encodedVideo'] = $encoded;
        }
        if ($uri !== null) {
            $video['uri'] = $uri;
        }

        return [
            'name' => $this->operation(),
            'done' => true,
            'response' => ['generateVideoResponse' => ['generatedSamples' => [['video' => $video]]]],
        ];
    }
}
