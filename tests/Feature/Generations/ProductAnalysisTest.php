<?php

declare(strict_types=1);

namespace Tests\Feature\Generations;

use App\Domains\Generations\Contracts\ProductImageAnalyzerInterface;
use App\Domains\Generations\Jobs\AnalyzeProductTrade;
use App\Domains\Generations\Services\ProductImageAnalyzer;
use App\Domains\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * تحلیل دورگهٔ محصول (بند ۱۲ — آیتم ۱): لایهٔ محلی همیشه، لایهٔ بینایی در صف.
 */
class ProductAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private const EDGE = 400;

    public function test_local_analysis_reports_dimensions_alpha_and_safe_zone(): void
    {
        Storage::fake('s3');
        $asset = $this->assetWith($this->pngWithAlpha());

        $local = app(ProductImageAnalyzerInterface::class)->analyzeLocally($asset);

        $this->assertSame(self::EDGE, $local['width']);
        $this->assertSame(self::EDGE, $local['height']);
        $this->assertSame(1.0, $local['ratio']);
        $this->assertTrue($local['has_alpha'], 'A transparent PNG must be flagged as having alpha.');
        $this->assertTrue($local['analyzable']);
        $this->assertSame(40, $local['safe_zone']['left']);
        $this->assertSame(360, $local['safe_zone']['right']);
    }

    public function test_local_analysis_detects_alpha_on_palette_png(): void
    {
        Storage::fake('s3');
        $asset = $this->assetWith($this->palettePngWithAlpha());

        $local = app(ProductImageAnalyzerInterface::class)->analyzeLocally($asset);

        $this->assertTrue($local['has_alpha'], 'A palette PNG with transparency must be flagged as having alpha.');
    }

    public function test_a_tiny_image_is_not_analyzable(): void
    {
        Storage::fake('s3');
        $image = imagecreatetruecolor(64, 64);
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();

        $local = app(ProductImageAnalyzer::class)->analyzeLocally($this->assetWith($bytes));

        $this->assertFalse($local['analyzable']);
    }

    public function test_the_endpoint_returns_local_analysis_immediately_and_queues_vision(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $asset = $this->assetWith($this->pngWithAlpha());
        $user = $asset->user;
        $product = $user->products()->create(['name' => 'انگشتر نقره']);
        $product->assets()->attach($asset->id, ['is_primary' => true]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/products/{$product->id}/analysis");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.local.width', self::EDGE)
            ->assertJsonPath('data.local.analyzable', true)
            ->assertJsonPath('data.suggestion_status', 'pending')
            ->assertJsonPath('data.suggestion', null);

        Queue::assertPushed(AnalyzeProductTrade::class, fn ($job) => $job->assetId === $asset->id);
    }

    public function test_a_cached_suggestion_is_returned_without_queueing(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $asset = $this->assetWith($this->pngWithAlpha());
        $user = $asset->user;
        $product = $user->products()->create(['name' => 'انگشتر نقره']);
        $product->assets()->attach($asset->id, ['is_primary' => true]);

        Cache::put(
            ProductImageAnalyzer::CACHE_PREFIX.$asset->id,
            ['category' => 'jewelry', 'confidence' => 0.92, 'style_hint' => 'studio', 'key_color' => '#C0C0C0'],
            60
        );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/products/{$product->id}/analysis");

        $response->assertOk()
            ->assertJsonPath('data.suggestion_status', 'ready')
            ->assertJsonPath('data.suggestion.category', 'jewelry')
            ->assertJsonPath('data.suggestion.confidence', 0.92);

        Queue::assertNothingPushed();
    }

    public function test_the_vision_layer_fills_the_cache_from_a_forgiving_json(): void
    {
        Storage::fake('s3');
        config(['ai.providers.google.api_key' => 'test-key']);
        $asset = $this->assetWith($this->pngWithAlpha());

        Http::fake(['*' => Http::response($this->visionResponse(
            "```json\n{\"category\":\"watches\",\"confidence\":0.87,\"style_hint\":\"minimal\",\"key_color\":\"#112233\"}\n```"
        ))]);

        app(ProductImageAnalyzer::class)->analyzeWithVision($asset);

        $cached = Cache::get(ProductImageAnalyzer::CACHE_PREFIX.$asset->id);

        $this->assertIsArray($cached);
        $this->assertSame('watches', $cached['category']);
        $this->assertSame(0.87, $cached['confidence']);
        $this->assertSame('minimal', $cached['style_hint']);
    }

    public function test_an_unknown_category_from_the_model_falls_back_to_general(): void
    {
        Storage::fake('s3');
        config(['ai.providers.google.api_key' => 'test-key']);
        $asset = $this->assetWith($this->pngWithAlpha());

        Http::fake(['*' => Http::response($this->visionResponse(
            '{"category":"spaceships","confidence":0.4}'
        ))]);

        app(ProductImageAnalyzer::class)->analyzeWithVision($asset);

        $cached = Cache::get(ProductImageAnalyzer::CACHE_PREFIX.$asset->id);

        $this->assertSame('general', $cached['category'], 'An out-of-set category must land on general.');
    }

    public function test_a_vision_failure_never_writes_a_suggestion(): void
    {
        Storage::fake('s3');
        config(['ai.providers.google.api_key' => 'test-key']);
        $asset = $this->assetWith($this->pngWithAlpha());

        Http::fake(fn () => Http::response('boom', 500));

        app(ProductImageAnalyzer::class)->analyzeWithVision($asset);

        $this->assertNull(Cache::get(ProductImageAnalyzer::CACHE_PREFIX.$asset->id));
    }

    public function test_a_recent_vision_failure_is_backed_off_not_requeued_per_request(): void
    {
        Storage::fake('s3');
        Queue::fake();
        config(['ai.providers.google.api_key' => 'test-key']);
        $asset = $this->assetWith($this->pngWithAlpha());
        $analyzer = app(ProductImageAnalyzer::class);

        Http::fake(fn () => Http::response('boom', 500));
        $analyzer->analyzeWithVision($asset);

        // Each /analysis hit must not stack up a fresh paid vision call;
        // the failure marker buys an hour of silence instead.
        $this->assertSame('unavailable', $analyzer->suggestion($asset)['status']);
        Queue::assertNotPushed(AnalyzeProductTrade::class);
    }

    public function test_a_product_without_assets_is_rejected(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'بدون عکس']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/products/{$product->id}/analysis");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'product.no_asset');
    }

    public function test_someone_elses_product_is_a_404(): void
    {
        Storage::fake('s3');
        $asset = $this->assetWith($this->pngWithAlpha());
        $owner = $asset->user;
        $product = $owner->products()->create(['name' => 'انگشتر نقره']);
        $product->assets()->attach($asset->id, ['is_primary' => true]);

        $intruder = User::factory()->create();

        $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/products/{$product->id}/analysis")
            ->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function visionResponse(string $text): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => $text]]],
            ]],
        ];
    }

    private function assetWith(string $bytes): MediaAsset
    {
        $user = User::factory()->create();
        $path = 'products/analysis-'.uniqid().'.png';
        Storage::disk('s3')->put($path, $bytes);

        return $user->mediaAssets()->create([
            'path' => $path,
            'disk' => 's3',
            'mime' => 'image/png',
            'size' => strlen($bytes),
        ]);
    }

    private function pngWithAlpha(): string
    {
        $image = imagecreatetruecolor(self::EDGE, self::EDGE);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);
        imagefilledrectangle(
            $image,
            40,
            40,
            self::EDGE - 40,
            self::EDGE - 40,
            imagecolorallocate($image, 230, 25, 25)
        );

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function palettePngWithAlpha(): string
    {
        $image = imagecreate(self::EDGE, self::EDGE);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagecolortransparent($image, $transparent);
        imagefill($image, 0, 0, $transparent);
        $red = imagecolorallocate($image, 230, 25, 25);
        imagefilledrectangle($image, 40, 40, self::EDGE - 40, self::EDGE - 40, $red);

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
