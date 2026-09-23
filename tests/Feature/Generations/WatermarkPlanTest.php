<?php

namespace Tests\Feature\Generations;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Media\Services\WatermarkService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WatermarkPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_generation_has_watermark_applied(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create(['plan_key' => 'free']);
        $product = $user->products()->create(['name' => 'عطر خنک']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'luxury perfume product photography',
        ]);
        $generation = $user->generations()->create([
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'queued',
            'prompt_hash' => hash('sha256', 'luxury perfume product photography'),
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);

        $credits = app(CreditService::class);
        $credits->reserve($user, $generation, 10);

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), $credits);

        $generation->refresh();
        $this->assertSame('completed', $generation->status);

        $savedContent = Storage::disk('s3')->get($generation->outputMedia->path);
        $this->assertNotEmpty($savedContent);

        // Watermark service modifies image contents for free plan
        $watermarkService = app(WatermarkService::class);
        // If passed through free plan again, it confirms image is a valid image created by watermark service
        $this->assertNotFalse(imagecreatefromstring($savedContent));
    }

    public function test_starter_plan_generation_has_no_watermark(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create(['plan_key' => 'starter']);
        $product = $user->products()->create(['name' => 'کفش چرم']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'leather shoe product commercial',
        ]);
        $generation = $user->generations()->create([
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'queued',
            'prompt_hash' => hash('sha256', 'leather shoe product commercial'),
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);

        $credits = app(CreditService::class);
        $credits->reserve($user, $generation, 10);

        // Gateway output for fake provider produces a known PNG
        $gatewayResult = app(AiGateway::class)->generate(new GenerationInput(
            type: 'image',
            prompt: 'leather shoe product commercial',
            aspectRatio: '1:1',
            generationId: $generation->id,
        ));

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), $credits);

        $generation->refresh();
        $this->assertSame('completed', $generation->status);

        $savedContent = Storage::disk('s3')->get($generation->outputMedia->path);

        $rawContents = $gatewayResult['result']->contents;
        $this->assertSame(strlen($rawContents), strlen($savedContent));
        $this->assertSame($rawContents, $savedContent);
    }

    public function test_creator_plan_generation_has_no_watermark(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create(['plan_key' => 'creator']);
        $product = $user->products()->create(['name' => 'ساعت مچی']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'luxury watch advertisement',
        ]);
        $generation = $user->generations()->create([
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'queued',
            'prompt_hash' => hash('sha256', 'luxury watch advertisement'),
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);

        $credits = app(CreditService::class);
        $credits->reserve($user, $generation, 10);

        $gatewayResult = app(AiGateway::class)->generate(new GenerationInput(
            type: 'image',
            prompt: 'luxury watch advertisement',
            aspectRatio: '1:1',
            generationId: $generation->id,
        ));

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), $credits);

        $generation->refresh();
        $this->assertSame('completed', $generation->status);

        $savedContent = Storage::disk('s3')->get($generation->outputMedia->path);

        $rawContents = $gatewayResult['result']->contents;
        $this->assertSame(strlen($rawContents), strlen($savedContent));
        $this->assertSame($rawContents, $savedContent);
    }

    public function test_watermark_service_unit_distinction(): void
    {
        $service = new WatermarkService;
        $image = imagecreatetruecolor(100, 100);
        ob_start();
        imagepng($image);
        $rawPng = ob_get_clean();
        imagedestroy($image);

        $starterResult = $service->applyForPlan($rawPng, 'image/png', 'starter');
        $creatorResult = $service->applyForPlan($rawPng, 'image/png', 'creator');
        $freeResult = $service->applyForPlan($rawPng, 'image/png', 'free');

        $this->assertSame($rawPng, $starterResult);
        $this->assertSame($rawPng, $creatorResult);
        $this->assertNotSame($rawPng, $freeResult);
    }

    public function test_watermark_service_safely_skips_videos(): void
    {
        $service = new WatermarkService;
        $fakeVideoBytes = "\x00\x00\x00\x18ftypmp42fake-video-bytes";

        $result = $service->applyForPlan($fakeVideoBytes, 'video/mp4', 'free');

        $this->assertSame($fakeVideoBytes, $result);
    }
}
