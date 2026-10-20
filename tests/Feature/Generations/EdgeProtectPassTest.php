<?php

namespace Tests\Feature\Generations;

use App\Domains\Generations\Services\EdgeProtectPass;
use App\Domains\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The second act of foreground-invariant inpainting: after the scene has
 * been rendered, the edit model is asked once more to land the product
 * from its own photo. The pass must promise exactly what the trade
 * catalogue promised, and a refusal must never become a broken run.
 */
class EdgeProtectPassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.providers.google.api_key' => 'test-key',
            'ai.edge_protect.enabled' => true,
        ]);
    }

    public function test_the_pass_answers_in_the_scene_mime_with_the_models_usage(): void
    {
        $answer = $this->pngBytes(16, 16);
        Http::fake(['*' => Http::response($this->answerPayload($answer, 'image/png'))]);

        $applied = app(EdgeProtectPass::class)->apply(
            $this->pngBytes(8, 8),
            'image/png',
            $this->productAsset(),
            'jewelry',
        );

        $this->assertNotNull($applied);
        $this->assertSame('image/png', $applied['mime']);
        $this->assertSame($answer, $applied['contents'], 'An answer already in the scene format must travel untouched.');
        $this->assertSame((string) config('ai.providers.google.edit_model'), $applied['model']);
        $this->assertSame((float) config('ai.edge_protect.cost_usd'), $applied['costUsd']);
        $this->assertSame(11, $applied['inputTokens']);
        $this->assertSame(7, $applied['outputTokens']);
    }

    public function test_the_request_carries_the_scene_then_the_product_then_the_trades_promises(): void
    {
        Http::fake(['*' => Http::response($this->answerPayload($this->pngBytes(16, 16), 'image/png'))]);

        $applied = app(EdgeProtectPass::class)
            ->apply($this->pngBytes(8, 8), 'image/png', $this->productAsset(), 'jewelry');

        $this->assertNotNull($applied);

        Http::assertSent(function ($request): bool {
            $body = json_decode($request->body(), true);
            $parts = $body['contents'][0]['parts'];

            $this->assertStringContainsString('gemini-2.5-flash-image:generateContent', $request->url());
            $this->assertCount(3, $parts, 'The pass speaks in three pieces: scene, product, promise.');
            $this->assertSame([0, 1], $this->imagePartIndexes($parts), 'Scene first, product photo second.');
            $this->assertSame(base64_encode($this->pngBytes(8, 8)), $parts[0]['inline_data']['data']);
            $this->assertSame(base64_encode(Storage::disk('s3')->get('products/real.png')), $parts[1]['inline_data']['data']);

            $prompt = (string) $parts[2]['text'];
            $this->assertStringContainsString('specular rim light', $prompt, 'The edge treatment is part of the recipe, not a hope.');
            $this->assertStringContainsString('light wrap', $prompt);
            $this->assertSame([], array_filter([$prompt], fn (string $p): bool => str_contains($p, '{')), 'The placeholders were filled from the catalogue, not left for the model.');
            $this->assertStringContainsString('macro photography, 100mm lens', $prompt, 'The trade scale lock rides along.');
            $this->assertStringContainsString('the texture of the chain', $prompt, 'The trade guardrail rides along.');

            return true;
        });
    }

    public function test_without_a_trade_the_prompt_carries_no_leftover_clauses(): void
    {
        Http::fake(['*' => Http::response($this->answerPayload($this->pngBytes(16, 16), 'image/png'))]);

        $applied = app(EdgeProtectPass::class)
            ->apply($this->pngBytes(8, 8), 'image/png', $this->productAsset(), null);

        $this->assertNotNull($applied);

        Http::assertSent(function ($request): bool {
            $prompt = (string) json_decode($request->body(), true)['contents'][0]['parts'][2]['text'];

            $this->assertStringNotContainsString('macro photography', $prompt, 'No trade means no borrowed scale.');
            $this->assertStringNotContainsString('{', $prompt, 'The template must never keep an unfilled placeholder.');

            return true;
        });
    }

    public function test_an_answer_in_another_format_is_re_encoded_to_the_scene_mime(): void
    {
        Http::fake(['*' => Http::response($this->answerPayload($this->jpegBytes(), 'image/jpeg'))]);

        $applied = app(EdgeProtectPass::class)->apply(
            $this->pngBytes(8, 8),
            'image/png',
            $this->productAsset(),
            null,
        );

        $this->assertNotNull($applied);
        $this->assertSame('image/png', $applied['mime'], 'The output contract already fixed the format.');

        $info = getimagesizefromstring($applied['contents']);
        $this->assertIsArray($info);
        $this->assertSame('image/png', (string) $info['mime']);
        $this->assertSame([10, 10], [$info[0], $info[1]]);
    }

    public function test_an_http_failure_is_a_refusal_to_decorate_not_a_broken_run(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->assertNull(app(EdgeProtectPass::class)->apply(
            $this->pngBytes(8, 8),
            'image/png',
            $this->productAsset(),
            null,
        ));
    }

    public function test_a_text_only_reply_never_counts_as_a_pass(): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [[
                'content' => ['parts' => [['text' => 'I cannot do that.']]],
            ]],
        ])]);

        $this->assertNull(app(EdgeProtectPass::class)->apply(
            $this->pngBytes(8, 8),
            'image/png',
            $this->productAsset(),
            null,
        ));
    }

    public function test_the_switch_off_costs_no_call(): void
    {
        config(['ai.edge_protect.enabled' => false]);
        Http::fake(['*' => Http::response($this->answerPayload($this->pngBytes(16, 16), 'image/png'))]);

        $this->assertNull(app(EdgeProtectPass::class)->apply(
            $this->pngBytes(8, 8),
            'image/png',
            $this->productAsset(),
            'jewelry',
        ));

        Http::assertNothingSent();
    }

    public function test_without_an_api_key_the_pass_stays_asleep(): void
    {
        config(['ai.providers.google.api_key' => null]);
        Http::fake(['*' => Http::response($this->answerPayload($this->pngBytes(16, 16), 'image/png'))]);

        $this->assertNull(app(EdgeProtectPass::class)->apply(
            $this->pngBytes(8, 8),
            'image/png',
            $this->productAsset(),
            'jewelry',
        ));

        Http::assertNothingSent();
    }

    /**
     * The index of every part that carries an image: the pass must show
     * the scene and the product photo before it speaks.
     *
     * @param  list<array<string, mixed>>  $parts
     * @return list<int>
     */
    private function imagePartIndexes(array $parts): array
    {
        $indexes = [];
        foreach ($parts as $index => $part) {
            if (! empty($part['inline_data']['data'])) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * @return array<string, mixed>
     */
    private function answerPayload(string $contents, string $mime): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [[
                    'inlineData' => ['mimeType' => $mime, 'data' => base64_encode($contents)],
                ]]],
            ]],
            'usageMetadata' => ['promptTokenCount' => 11, 'candidatesTokenCount' => 7],
        ];
    }

    private function productAsset(): MediaAsset
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $bytes = $this->pngBytes(14, 14);
        Storage::disk('s3')->put('products/real.png', $bytes);

        return $user->mediaAssets()->create([
            'path' => 'products/real.png',
            'disk' => 's3',
            'mime' => 'image/png',
            'size' => strlen($bytes),
        ]);
    }

    /** A blue scene and a red answer, differencing only by size exactly one. */
    private function pngBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, $width === 8 ? 12 : 200, $width === 8 ? 40 : 30, $width === 8 ? 90 : 30));

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    private function jpegBytes(): string
    {
        $image = imagecreatetruecolor(10, 10);
        imagefill($image, 0, 0, imagecolorallocate($image, 250, 200, 40));

        ob_start();
        imagejpeg($image, null, 90);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
