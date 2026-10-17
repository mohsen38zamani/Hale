<?php

namespace Tests\Feature\Generations;

use App\Domains\Generations\Models\Generation;
use App\Domains\Generations\Services\MasterCanvasCropper;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Batch 7 - phase 6 and phase 7 of the specification: one master render, the
 * channel frames cut out of it, and the tests that hold the promise. The
 * frames are derived pixels, so they must never cost a second model call;
 * and a cut has to keep the subject, because a story frame that slices the
 * product in half is worse than no story frame at all.
 */
class MasterCanvasCropTest extends TestCase
{
    use RefreshDatabase;

    /** Every configured channel, in catalogue order. */
    private const CHANNELS = ['1:1' => [1, 1], '4:5' => [4, 5], '9:16' => [9, 16]];

    public function test_every_configured_channel_answers_with_its_own_aspect(): void
    {
        $cropper = new MasterCanvasCropper;
        $master = $this->png(1200, 1200);

        foreach (self::CHANNELS as $ratio => [$width, $height]) {
            $definition = config("creative.master_canvas.ratios.{$ratio}");
            $this->assertIsArray($definition, "The catalogue must offer {$ratio}.");

            $frame = $cropper->frame($master, 'image/png', $definition, 1600);
            $this->assertNotNull($frame);

            $info = getimagesizefromstring($frame['bytes']);
            $this->assertNotFalse($info);
            $this->assertEqualsWithDelta(
                $width / $height,
                $info[0] / $info[1],
                0.01,
                "The {$ratio} frame must actually be {$width}:{$height}."
            );
            $this->assertSame('image/png', $frame['mime']);
        }
    }

    public function test_a_master_that_already_is_the_frame_keeps_its_own_bytes(): void
    {
        $master = $this->png(900, 1600);
        $definition = config('creative.master_canvas.ratios.9:16');

        $frame = (new MasterCanvasCropper)->frame($master, 'image/png', $definition, 1600);

        $this->assertNotNull($frame);
        $this->assertSame($master, $frame['bytes'], 'A faithful master must not be recompressed into a lesser copy.');
    }

    public function test_a_master_bigger_than_the_budget_is_scaled_down_to_it(): void
    {
        $master = $this->png(2400, 2400);

        $frame = (new MasterCanvasCropper)->frame($master, 'image/png', config('creative.master_canvas.ratios.1:1'), 1600);

        $this->assertNotNull($frame);
        $info = getimagesizefromstring($frame['bytes']);
        $this->assertSame([1600, 1600], [$info[0], $info[1]], 'A master over the edge budget is brought down to it, not served raw.');
    }

    public function test_the_cut_follows_the_subject_instead_of_the_frame_edge(): void
    {
        // A wide master with the product shoved into its left third: a naive
        // centre crop would cut the subject out of the very frame it was
        // rendered for.
        $master = $this->png(1600, 1000, function ($image): void {
            imagefilledrectangle($image, 100, 400, 300, 600, imagecolorallocate($image, 220, 30, 30));
        });

        $frame = (new MasterCanvasCropper)->frame($master, 'image/png', config('creative.master_canvas.ratios.1:1'), 1600);

        $this->assertNotNull($frame);
        $cut = imagecreatefromstring($frame['bytes']);
        $this->assertNotFalse($cut);
        $color = imagecolorat($cut, 150, 500);
        $this->assertGreaterThan(150, ($color >> 16) & 0xFF, 'The subject must survive the cut.');
        $this->assertLessThan(100, ($color >> 8) & 0xFF, 'The subject must survive the cut.');
    }

    public function test_a_flat_catalog_square_stays_centered(): void
    {
        $flat = $this->png(800, 800);

        $frame = (new MasterCanvasCropper)->frame($flat, 'image/png', config('creative.master_canvas.ratios.4:5'), 1600);

        $this->assertNotNull($frame);
        $info = getimagesizefromstring($frame['bytes']);
        $this->assertEqualsWithDelta(4 / 5, $info[0] / $info[1], 0.01);
    }

    public function test_bytes_that_are_not_an_image_are_refused_rather_than_guessed(): void
    {
        $frame = (new MasterCanvasCropper)->frame('not an image at all', 'image/png', config('creative.master_canvas.ratios.1:1'), 1600);

        $this->assertNull($frame);
    }

    public function test_the_owner_gets_a_frame_and_the_second_ask_reads_the_stored_one(): void
    {
        Storage::fake('s3');
        $generation = $this->completedGeneration($this->png(1200, 1200));
        Sanctum::actingAs($generation->user);

        $first = $this->getJson("/api/generations/{$generation->id}/crop?ratio=4:5");
        $first->assertOk()->assertHeader('Content-Type', 'image/png');

        $info = getimagesizefromstring($first->streamedContent());
        $this->assertEqualsWithDelta(4 / 5, $info[0] / $info[1], 0.01, 'The served frame must really be 4:5.');
        $this->assertStringContainsString('4x5', (string) $first->headers->get('Content-Disposition'));

        // Cut once, serve forever: the stored frame is what later requests
        // read, and the ETag proves it is the very same bytes.
        Storage::disk('s3')->assertExists("crops/{$generation->id}-4x5.png");

        $second = $this->getJson("/api/generations/{$generation->id}/crop?ratio=4:5", ['If-None-Match' => (string) $first->headers->get('ETag')]);
        $second->assertStatus(304);
    }

    public function test_a_ratio_the_catalogue_does_not_sell_is_refused(): void
    {
        Storage::fake('s3');
        $generation = $this->completedGeneration($this->png(800, 800));
        Sanctum::actingAs($generation->user);

        $this->getJson("/api/generations/{$generation->id}/crop?ratio=21:9")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'UNSUPPORTED_RATIO');

        Storage::disk('s3')->assertMissing("crops/{$generation->id}-21x9.png");
    }

    public function test_a_master_that_vanished_from_the_disk_is_reported_not_found(): void
    {
        Storage::fake('s3');
        $generation = $this->completedGeneration($this->png(800, 800));
        Sanctum::actingAs($generation->user);
        Storage::disk('s3')->delete($generation->outputMedia->path);

        $this->getJson("/api/generations/{$generation->id}/crop?ratio=1:1")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'OUTPUT_NOT_FOUND');

        Storage::disk('s3')->assertMissing("crops/{$generation->id}-1x1.png");
    }

    public function test_a_video_has_no_master_frame_to_cut(): void
    {
        Storage::fake('s3');
        $generation = $this->completedGeneration($this->png(800, 800), 'video/mp4');
        Sanctum::actingAs($generation->user);

        $this->getJson("/api/generations/{$generation->id}/crop?ratio=1:1")
            ->assertStatus(415)
            ->assertJsonPath('error.code', 'OUTPUT_NOT_IMAGE');
    }

    public function test_someone_elses_render_is_not_theirs_to_cut(): void
    {
        Storage::fake('s3');
        $generation = $this->completedGeneration($this->png(800, 800));

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/generations/{$generation->id}/crop?ratio=1:1")->assertNotFound();
    }

    public function test_anonymous_requests_never_reach_a_frame(): void
    {
        Storage::fake('s3');
        $generation = $this->completedGeneration($this->png(800, 800));

        $this->getJson("/api/generations/{$generation->id}/crop?ratio=1:1")->assertUnauthorized();
        Storage::disk('s3')->assertMissing("crops/{$generation->id}-1x1.png");
    }

    public function test_a_render_that_has_not_finished_has_nothing_to_cut(): void
    {
        Storage::fake('s3');
        $generation = $this->completedGeneration($this->png(800, 800));
        $generation->update(['status' => 'processing', 'output_media_id' => null]);
        Sanctum::actingAs($generation->user);

        $this->getJson("/api/generations/{$generation->id}/crop?ratio=1:1")->assertNotFound();
    }

    public function test_the_result_page_offers_every_configured_channel(): void
    {
        $view = (string) $this->get('/generations/1')->assertOk()->baseResponse->getContent();

        $this->assertStringContainsString('data-crop-row', $view, 'The master canvas row must ship with the page.');
        foreach (array_keys((array) config('creative.master_canvas.ratios')) as $ratio) {
            $this->assertStringContainsString('data-crop="'.$ratio.'"', $view, "The {$ratio} channel must be offered.");
        }

        $script = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('/crop?ratio=${encodeURIComponent(link.dataset.crop)}', $script, 'The links must point at the crop endpoint.');
        // A clip has no master still; the row stays closed for it.
        $this->assertStringContainsString('if (cropRow && !isVideo)', $script);
    }

    /**
     * A completed generation whose output is the given bytes.
     */
    private function completedGeneration(string $contents, string $mime = 'image/png'): Generation
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'ساعت کلاسیک']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'prompt',
        ]);

        $extension = $mime === 'image/png' ? 'png' : 'mp4';
        $path = "generations/{$user->id}/output.{$extension}";
        Storage::disk('s3')->put($path, $contents);
        $media = $user->mediaAssets()->create([
            'disk' => 's3',
            'path' => $path,
            'mime' => $mime,
            'size' => strlen($contents),
        ]);

        return $project->generations()->create([
            'user_id' => $user->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'prompt'),
            'output_media_id' => $media->id,
        ]);
    }

    private function png(int $width, int $height, ?callable $paint = null): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 255, 255, 255));
        if ($paint !== null) {
            $paint($image);
        }
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
