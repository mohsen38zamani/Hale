<?php

namespace Tests\Feature\Creative;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\AI\Router\ModelRouter;
use App\Domains\Creative\Services\ProductCompositor;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The catalog-white frame: the product's own pixels on pure white under a
 * grounding shadow, and the wiring that hands that frame to the model when
 * - and only when - the project chose it.
 */
class ProductCompositorTest extends TestCase
{
    use RefreshDatabase;

    private const CUTOUT_EDGE = 200;

    public function test_the_composite_puts_the_product_on_pure_white(): void
    {
        $composited = (new ProductCompositor)->composite($this->cutout());
        $image = imagecreatefromstring($composited);

        $this->assertSame(1600, imagesx($image), 'The catalog frame is the fixed square.');
        $this->assertSame(1600, imagesy($image));

        foreach ([[5, 5], [1595, 5], [5, 1595], [1595, 1595]] as [$x, $y]) {
            $pixel = imagecolorsforindex($image, imagecolorat($image, $x, $y));
            $this->assertSame(
                ['red' => 255, 'green' => 255, 'blue' => 255],
                ['red' => $pixel['red'], 'green' => $pixel['green'], 'blue' => $pixel['blue']],
                "Corner ({$x},{$y}) is not pure white."
            );
        }
    }

    public function test_the_products_own_pixels_survive_untouched(): void
    {
        $composited = (new ProductCompositor)->composite($this->cutout());
        $image = imagecreatefromstring($composited);

        // The cutout is smaller than the canvas, so it is pasted, not
        // resampled: the exact red that went in has to be the red that
        // comes out, dead center of the product.
        $center = imagecolorsforindex($image, imagecolorat($image, 800, 780));

        $this->assertSame(
            ['red' => 230, 'green' => 25, 'blue' => 25],
            ['red' => $center['red'], 'green' => $center['green'], 'blue' => $center['blue']],
            'The compositor may not repaint a single pixel of the product.'
        );
    }

    public function test_a_soft_shadow_greys_the_floor_under_the_product(): void
    {
        $composited = (new ProductCompositor)->composite($this->cutout());
        $image = imagecreatefromstring($composited);

        // Just under the product's bottom edge, inside the shadow pool:
        // gray (channels close together) and darker than the white floor.
        $floor = imagecolorsforindex($image, imagecolorat($image, 800, 890));

        $this->assertLessThan(230, $floor['red'], 'The shadow pool must sit clearly below the white floor.');
        $this->assertLessThan(15, abs($floor['red'] - $floor['blue']), 'The shadow must be gray, not a colored cast.');
    }

    public function test_unreadable_bytes_are_refused_loudly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('تصویر محصول قابل خواندن نیست.');

        (new ProductCompositor)->composite('these are not image bytes');
    }

    public function test_the_catalog_frame_reaches_the_provider_and_leaves_no_litter(): void
    {
        Storage::fake('s3');
        $provider = $this->recordingProvider();
        $generation = $this->queuedGeneration(['fidelity_mode' => 'catalog_white_composite'], $this->cutout());

        (new ProcessGeneration($generation->id))
            ->handle(new AiGateway(new ModelRouter([$provider])), app(CreditService::class));

        $this->assertSame('completed', $generation->refresh()->status);

        $received = imagecreatefromstring((string) $provider->receivedAsset);
        $this->assertSame(
            1600,
            imagesx($received),
            'The provider must receive the composited catalog frame, not the raw cutout.'
        );

        $leftovers = array_values(array_filter(
            Storage::disk('s3')->allFiles(),
            fn (string $path): bool => str_starts_with($path, 'composites/')
        ));
        $this->assertSame([], $leftovers, 'The staged composite must not outlive the call.');
    }

    public function test_without_the_composite_mode_the_original_photo_travels_untouched(): void
    {
        Storage::fake('s3');
        $provider = $this->recordingProvider();
        $cutout = $this->cutout();
        $generation = $this->queuedGeneration([], $cutout);

        (new ProcessGeneration($generation->id))
            ->handle(new AiGateway(new ModelRouter([$provider])), app(CreditService::class));

        $this->assertSame('completed', $generation->refresh()->status);
        $this->assertSame(
            $cutout,
            $provider->receivedAsset,
            'A project that did not choose the catalog frame must send the product photo exactly as uploaded.'
        );
    }

    /**
     * A provider that only watches: what matters here is the photo it is
     * handed, not the picture it answers with.
     */
    private function recordingProvider(): GenerationProvider
    {
        return new class implements GenerationProvider
        {
            public ?string $receivedAsset = null;

            public function key(): string
            {
                return 'recording';
            }

            public function supports(GenerationInput $input): bool
            {
                return true;
            }

            public function generate(GenerationInput $input): GenerationResult
            {
                if ($input->assetDisk !== null && $input->assetPath !== null) {
                    $this->receivedAsset = (string) Storage::disk($input->assetDisk)->get($input->assetPath);
                }

                $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

                return new GenerationResult($png, 'image/png', 'png', 'recording', 0);
            }
        };
    }

    /**
     * @param  array<string, mixed>  $project
     */
    private function queuedGeneration(array $project, string $assetBytes): Generation
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'انگشتر نقره']);
        $asset = $user->mediaAssets()->create(['path' => 'products/sample.png', 'disk' => 's3', 'mime' => 'image/png', 'size' => strlen($assetBytes)]);
        Storage::disk('s3')->put('products/sample.png', $assetBytes);
        $product->assets()->attach($asset->id, ['is_primary' => true]);

        $creative = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'professional',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'prompt',
            ...$project,
        ]);

        $generation = $user->generations()->create([
            'creative_project_id' => $creative->id,
            'type' => 'image',
            'status' => 'queued',
            'prompt_hash' => hash('sha256', 'prompt'),
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);

        app(CreditService::class)->reserve($user, $generation, 10);

        return $generation;
    }

    private function cutout(): string
    {
        $image = imagecreatetruecolor(self::CUTOUT_EDGE, self::CUTOUT_EDGE);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);
        imagefilledrectangle(
            $image,
            40,
            40,
            self::CUTOUT_EDGE - 40,
            self::CUTOUT_EDGE - 40,
            imagecolorallocate($image, 230, 25, 25)
        );

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
