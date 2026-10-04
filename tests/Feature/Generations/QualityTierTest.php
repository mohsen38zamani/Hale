<?php

namespace Tests\Feature\Generations;

use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QualityTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_plan_cannot_request_premium_quality(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $user->products()->create(['name' => 'عطر']);

        $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'quality' => 'premium',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PREMIUM_QUALITY_REQUIRED');

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(config('credits.initial_balance'), app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_standard_tier_is_the_default_and_reserves_ten_credits(): void
    {
        Queue::fake();
        Sanctum::actingAs($user = User::factory()->create());
        $product = $user->products()->create(['name' => 'عطر']);

        $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
        ])->assertAccepted();

        $generation = Generation::query()->latest('id')->firstOrFail();
        $this->assertSame('standard', $generation->metadata['quality']);
        $this->assertSame(10, $generation->credits_reserved);
        $this->assertSame(config('credits.initial_balance') - 10, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_paid_plan_can_request_premium_and_reserves_twenty_five_credits(): void
    {
        Queue::fake();
        Sanctum::actingAs($user = User::factory()->create());
        $user->update(['plan_key' => 'starter']);
        app(CreditService::class)->account($user)->forceFill(['balance' => 100])->save();
        $product = $user->products()->create(['name' => 'عطر']);

        $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'quality' => 'premium',
        ])->assertAccepted();

        $generation = Generation::query()->latest('id')->firstOrFail();
        $this->assertSame('premium', $generation->metadata['quality']);
        $this->assertSame(25, $generation->credits_reserved);
        $this->assertSame(75, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_bulk_rejects_premium_for_the_free_plan(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $id = $user->products()->create(['name' => 'عطر'])->id;

        $this->postJson('/api/generations/bulk', [
            'product_ids' => [$id],
            'quality' => 'premium',
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'PREMIUM_QUALITY_REQUIRED');

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_estimate_endpoint_exposes_tier_cost_and_plan_gate(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->postJson('/api/credits/estimate', ['type' => 'image', 'quality' => 'premium'])
            ->assertOk()
            ->assertJsonPath('data.quality', 'standard')
            ->assertJsonPath('data.quality_blocked', true)
            ->assertJsonPath('data.cost', 10);

        $user->update(['plan_key' => 'creator']);

        $this->postJson('/api/credits/estimate', ['type' => 'image', 'quality' => 'premium'])
            ->assertOk()
            ->assertJsonPath('data.quality', 'premium')
            ->assertJsonPath('data.quality_blocked', false)
            ->assertJsonPath('data.cost', 25);

        $this->postJson('/api/credits/estimate', ['type' => 'image', 'quality' => 'ultra'])->assertUnprocessable();
    }

    public function test_standard_output_is_capped_at_one_k(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $longEdge = $this->processWithSource($user, 'standard', 1500);

        $this->assertSame(1024, $longEdge);
    }

    public function test_premium_output_targets_two_k(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $longEdge = $this->processWithSource($user, 'premium', 1500);

        $this->assertSame(2048, $longEdge);
    }

    public function test_tiny_placeholder_outputs_are_never_upscaled(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $longEdge = $this->processWithSource($user, 'premium', 1);

        $this->assertSame(1, $longEdge);
    }

    public function test_profile_exposes_quota_usage(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $response = $this->getJson('/api/user/profile')->assertOk();
        $response->assertJsonPath('data.usage.image.limit', config('plans.free.image_limit'))
            ->assertJsonPath('data.usage.image.used', 0)
            ->assertJsonPath('data.usage.image.remaining', config('plans.free.image_limit'))
            ->assertJsonPath('data.usage.video.limit', 0);
    }

    public function test_pricing_endpoint_advertises_quality_tiers(): void
    {
        $data = $this->getJson('/api/plans')->assertOk()->json('data');

        $this->assertSame('standard', collect($data)->firstWhere('key', 'free')['quality']);
        $this->assertSame('premium', collect($data)->firstWhere('key', 'starter')['quality']);
        $this->assertSame('premium', collect($data)->firstWhere('key', 'creator')['quality']);
    }

    /**
     * Run a queued image generation whose provider returns a square PNG of
     * the given size, then report the long edge of the stored output.
     */
    private function processWithSource(User $user, string $quality, int $sourceSize): int
    {
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);
        $generation = $user->generations()->create([
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'queued',
            'prompt_hash' => hash('sha256', 'prompt'),
            'metadata' => ['aspect_ratio' => '1:1', 'quality' => $quality],
        ]);
        app(CreditService::class)->reserve($user, $generation, 10);

        $source = $this->png($sourceSize);
        $gateway = \Mockery::mock(AiGateway::class);
        $gateway->shouldReceive('generate')->once()->andReturn([
            'provider' => 'local',
            'result' => new GenerationResult($source, 'image/png', 'png', 'local-preview-v1', 0),
            'failures' => [],
        ]);

        (new ProcessGeneration($generation->id))->handle($gateway, app(CreditService::class));

        $this->assertSame('completed', $generation->fresh()->status);
        $contents = Storage::disk('s3')->get($generation->fresh()->outputMedia->path);
        $size = getimagesizefromstring($contents);

        return max((int) $size[0], (int) $size[1]);
    }

    private function png(int $size): string
    {
        $image = imagecreatetruecolor($size, $size);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 60));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
