<?php

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDisk(): void
    {
        Storage::fake('local');
        config(['filesystems.media_disk' => 'local']);
    }

    /**
     * A deterministic solid-color PNG of the requested size.
     */
    private function pngBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 24, 36, 52));
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    public function test_upload_builds_a_web_variant_within_the_dimension_limit(): void
    {
        $this->fakeDisk();
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'کفش']);
        Sanctum::actingAs($user);

        $asset = $this->postJson("/api/products/{$product->id}/assets", [
            'image' => UploadedFile::fake()->image('shoe.png', 2400, 1800),
        ])->assertCreated()->json('data');

        $this->assertNotNull($asset['web_path']);
        $disk = Storage::disk('local');
        $this->assertTrue($disk->exists($asset['web_path']));

        $info = getimagesizefromstring($disk->get($asset['web_path']));
        $this->assertNotFalse($info);
        $this->assertSame(1600, $info[0]);
        $this->assertSame(1200, $info[1]);
        $this->assertSame('image/webp', $info['mime']);
    }

    public function test_web_dimension_follows_configuration(): void
    {
        $this->fakeDisk();
        config(['media.web_max_dimension' => 500]);
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'کیف']);
        Sanctum::actingAs($user);

        $asset = $this->postJson("/api/products/{$product->id}/assets", [
            'image' => UploadedFile::fake()->image('bag.png', 2000, 1000),
        ])->assertCreated()->json('data');

        $info = getimagesizefromstring(Storage::disk('local')->get($asset['web_path']));
        $this->assertNotFalse($info);
        $this->assertSame(500, $info[0]);
        $this->assertSame(250, $info[1]);
    }

    public function test_asset_download_serves_original_and_cached_web_variants(): void
    {
        $this->fakeDisk();
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'عطر']);
        Sanctum::actingAs($user);

        $asset = $user->mediaAssets()->create([
            'disk' => 'local',
            'path' => 'users/'.$user->id.'/products/original.png',
            'mime' => 'image/png',
            'size' => 10,
        ]);
        $original = $this->pngBytes(2000, 1500);
        Storage::disk('local')->put($asset->path, $original);
        $product->assets()->attach($asset->id, ['is_primary' => true]);

        $uri = "/api/products/{$product->id}/assets/{$asset->id}/download";

        // ?variant=original keeps the untouched bytes and supports revalidation.
        $originalResponse = $this->get($uri.'?variant=original');
        $originalResponse->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame($original, $originalResponse->streamedContent());
        $originalEtag = (string) $originalResponse->headers->get('ETag');
        $this->assertNotSame('', $originalEtag);
        $this->get($uri.'?variant=original', ['If-None-Match' => $originalEtag])->assertStatus(304);

        // ?variant=web lazily creates and serves the compressed WebP.
        $web = $this->get($uri.'?variant=web');
        $web->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->assertNotNull($asset->fresh()->web_path);
        $info = getimagesizefromstring($web->streamedContent());
        $this->assertNotFalse($info);
        $this->assertSame(1600, $info[0]);
        $this->assertStringContainsString('private', (string) $web->headers->get('Cache-Control'));

        // Conditional request: the ETag from the first hit yields a 304.
        $etag = (string) $web->headers->get('ETag');
        $this->assertNotSame('', $etag);
        $this->get($uri.'?variant=web', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_web_variant_serves_small_webp_sources_untouched(): void
    {
        $this->fakeDisk();
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'گلدان']);
        Sanctum::actingAs($user);

        $asset = $user->mediaAssets()->create([
            'disk' => 'local',
            'path' => 'users/'.$user->id.'/products/small.webp',
            'mime' => 'image/webp',
            'size' => 10,
        ]);
        $original = $this->pngBytes(800, 600);
        Storage::disk('local')->put($asset->path, $original);
        $product->assets()->attach($asset->id, ['is_primary' => true]);

        $response = $this->get("/api/products/{$product->id}/assets/{$asset->id}/download?variant=web");
        $response->assertOk()->assertHeader('Content-Type', 'image/webp');
        // Already an in-limit WebP: no re-encode, original bytes are served.
        $this->assertSame($original, $response->streamedContent());
        $this->assertNull($asset->fresh()->web_path);
    }

    public function test_generation_output_offers_web_preview_and_untouched_download(): void
    {
        $this->fakeDisk();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);
        $media = $user->mediaAssets()->create(['disk' => 'local', 'path' => 'generations/result.png', 'mime' => 'image/png', 'size' => 10]);
        $original = $this->pngBytes(2400, 2400);
        Storage::disk('local')->put($media->path, $original);
        $generation = $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'completed', 'prompt_hash' => hash('sha256', 'prompt'), 'output_media_id' => $media->id]);

        // Default download is untouched and keeps its filename.
        $this->get("/api/generations/{$generation->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertDownload("generation-{$generation->id}.png");

        // The web variant is compressed to the configured limit and renamed.
        $web = $this->get("/api/generations/{$generation->id}/download?variant=web");
        $web->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertDownload("generation-{$generation->id}.webp");
        $info = getimagesizefromstring($web->streamedContent());
        $this->assertNotFalse($info);
        $this->assertSame(1600, $info[0]);
        $this->assertNotNull($media->fresh()->web_path);
        $this->assertStringContainsString('private', (string) $web->headers->get('Cache-Control'));

        // Repeat request with the ETag short-circuits to 304.
        $this->get("/api/generations/{$generation->id}/download?variant=web", ['If-None-Match' => (string) $web->headers->get('ETag')])
            ->assertStatus(304);
    }

    public function test_non_image_and_undecodable_outputs_fall_back_to_the_original(): void
    {
        $this->fakeDisk();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'کفش']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_reel', 'brief' => [], 'prompt' => 'prompt']);

        $video = $user->mediaAssets()->create(['disk' => 'local', 'path' => 'generations/clip.mp4', 'mime' => 'video/mp4', 'size' => 4]);
        Storage::disk('local')->put($video->path, 'mp4-bytes');
        $videoGeneration = $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'video', 'status' => 'completed', 'prompt_hash' => hash('sha256', 'video'), 'output_media_id' => $video->id]);
        $this->get("/api/generations/{$videoGeneration->id}/download?variant=web")
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertDownload("generation-{$videoGeneration->id}.mp4");

        $corrupt = $user->mediaAssets()->create(['disk' => 'local', 'path' => 'generations/broken.png', 'mime' => 'image/png', 'size' => 4]);
        Storage::disk('local')->put($corrupt->path, 'data');
        $broken = $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'completed', 'prompt_hash' => hash('sha256', 'broken'), 'output_media_id' => $corrupt->id]);
        $this->get("/api/generations/{$broken->id}/download?variant=web")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->assertNull($corrupt->fresh()->web_path);
    }
}
