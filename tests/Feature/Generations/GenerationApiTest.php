<?php

namespace Tests\Feature\Generations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_estimate_image_credit_cost(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/credits/estimate', ['type' => 'image'])
            ->assertOk()
            ->assertJsonPath('data.cost', 10)
            ->assertJsonPath('data.sufficient', true);
    }

    public function test_video_estimate_reports_insufficient_balance(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/credits/estimate', ['type' => 'video', 'video_duration_seconds' => 5])
            ->assertOk()
            ->assertJsonPath('data.cost', 45)
            ->assertJsonPath('data.sufficient', false)
            ->assertJsonPath('data.pricing_url', '/pricing');
    }

    public function test_user_can_preview_auto_best_and_queue_generation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);
        $preview = $this->postJson('/api/creative/preview', ['product_id' => $product->id, 'goal' => 'sales'])
            ->assertOk()
            ->assertJsonPath('data.goal', 'sales')
            ->assertJsonStructure(['data' => ['goal', 'style', 'format', 'environment', 'brief', 'prompt_preview', 'estimated_credits', 'type']]);
        $id = $this->postJson('/api/generations', ['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'environment' => 'studio'])->assertStatus(202)->assertJsonPath('data.status', 'queued')->json('data.id');
        $this->getJson("/api/generations/{$id}")->assertOk()->assertJsonPath('data.creative_project.product.name', 'عطر');
    }

    public function test_video_duration_is_required_for_video_format(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'کفش']);
        $this->postJson('/api/generations', ['product_id' => $product->id, 'goal' => 'branding', 'style' => 'fashion', 'format' => 'instagram_reel'])->assertUnprocessable()->assertJsonValidationErrors('video_duration_seconds');
    }

    public function test_free_plan_cannot_generate_video(): void
    {
        $user = User::factory()->create(['plan_key' => 'free']);
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'کفش']);

        $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'branding',
            'style' => 'fashion',
            'format' => 'instagram_reel',
            'video_duration_seconds' => 5,
        ])->assertStatus(402)->assertJsonPath('error.code', 'PLAN_LIMIT_REACHED');
    }

    public function test_user_can_regenerate_a_completed_generation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'prompt',
        ]);
        $generation = $user->generations()->create([
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'prompt'),
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);

        $newGeneration = $this->postJson("/api/generations/{$generation->id}/regenerate")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'queued')
            ->json('data');

        $this->assertNotSame($generation->id, $newGeneration['id']);
        $this->assertDatabaseHas('generations', [
            'id' => $newGeneration['id'],
            'creative_project_id' => $project->id,
        ]);
        $this->assertDatabaseHas('credit_transactions', [
            'generation_id' => $newGeneration['id'],
            'type' => 'charge',
            'amount' => 10,
        ]);
    }

    public function test_owner_can_submit_feedback_for_completed_generation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);
        $generation = $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'completed', 'prompt_hash' => hash('sha256', 'prompt')]);

        $this->postJson("/api/generations/{$generation->id}/feedback", ['feedback' => 'positive'])
            ->assertOk()
            ->assertJsonPath('data.feedback', 'positive');
    }

    public function test_generation_status_exposes_credit_usage(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);
        $generation = $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'processing', 'prompt_hash' => hash('sha256', 'prompt'), 'credits_reserved' => 10]);

        $this->getJson("/api/generations/{$generation->id}")
            ->assertOk()
            ->assertJsonPath('data.credits_reserved', 10)
            ->assertJsonPath('data.credits_charged', 0);
    }

    public function test_generation_history_can_be_filtered_by_type_and_date(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);
        $image = $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'completed', 'prompt_hash' => hash('sha256', 'image')]);
        $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'video', 'status' => 'completed', 'prompt_hash' => hash('sha256', 'video')]);

        $this->getJson('/api/generations?type=image&from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.id', $image->id);
    }

    public function test_manual_retry_is_limited_to_two_attempts(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);
        $generation = $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'failed', 'prompt_hash' => hash('sha256', 'retry')]);

        foreach ([1, 2] as $attempt) {
            $this->postJson("/api/generations/{$generation->id}/retry")->assertAccepted();
            $generation->refresh()->update(['status' => 'failed', 'credits_reserved' => 0]);
            $user->creditAccount()->update(['reserved' => 0]);
        }

        $this->postJson("/api/generations/{$generation->id}/retry")->assertConflict();
    }

    public function test_owner_can_download_completed_generation_output(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);
        $media = $user->mediaAssets()->create(['disk' => 'local', 'path' => 'generations/result.png', 'mime' => 'image/png', 'size' => 4]);
        Storage::disk('local')->put($media->path, 'data');
        $generation = $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'completed', 'prompt_hash' => hash('sha256', 'prompt'), 'output_media_id' => $media->id]);

        $this->get("/api/generations/{$generation->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertDownload("generation-{$generation->id}.png");
    }

    public function test_user_can_create_generation_with_custom_prompt(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر لوکس']);

        $response = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'environment' => 'studio',
            'custom_prompt' => 'روی سنگ بازالت مرطوب با گل‌های صورتی ارکیده',
        ])->assertAccepted();

        $generationId = $response->json('data.id');
        $generation = $user->generations()->with('creativeProject')->findOrFail($generationId);

        $this->assertSame('روی سنگ بازالت مرطوب با گل‌های صورتی ارکیده', $generation->creativeProject->custom_prompt);
        $this->assertSame('روی سنگ بازالت مرطوب با گل‌های صورتی ارکیده', $generation->creativeProject->brief['custom_prompt']);
        $this->assertStringContainsString('Custom scene details: روی سنگ بازالت مرطوب با گل‌های صورتی ارکیده.', $generation->creativeProject->prompt);
    }

    public function test_custom_prompt_cannot_exceed_max_length(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر لوکس']);

        $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'custom_prompt' => str_repeat('س', 1001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_prompt' => 'توضیحات دلخواه نمی‌تواند بیش از ۱۰۰۰ کاراکتر باشد.']);
    }

    public function test_custom_prompt_is_moderated_and_rejected_if_inappropriate(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر لوکس']);

        $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'custom_prompt' => 'تصویر با پس‌زمینه پورنوگرافی و مستهجن',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_prompt' => 'توضیحات دلخواه با قوانین محتوایی سازگار نیست.']);
    }

    public function test_user_can_create_generation_with_scene_controls(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'ساعت مچی لوکس']);

        $response = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'branding',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'environment' => 'studio',
            'surface' => 'marble',
            'props' => 'botanical',
            'camera_angle' => 'hero_shot',
            'lighting_setup' => 'rim',
        ])->assertAccepted();

        $generationId = $response->json('data.id');
        $generation = $user->generations()->with('creativeProject')->findOrFail($generationId);

        $this->assertSame('marble', $generation->creativeProject->surface);
        $this->assertSame('botanical', $generation->creativeProject->props);
        $this->assertSame('hero_shot', $generation->creativeProject->camera_angle);
        $this->assertSame('rim', $generation->creativeProject->lighting_setup);

        $this->assertStringContainsString('Displayed on a luxury white veined Carrara marble pedestal with soft specular highlights.', $generation->creativeProject->prompt);
        $this->assertStringContainsString('Accented with lush monstera leaves, olive branches, and delicate pink orchid petals.', $generation->creativeProject->prompt);
        $this->assertStringContainsString('Camera perspective: powerful low-angle heroic viewpoint creating grand scale and presence.', $generation->creativeProject->prompt);
        $this->assertStringContainsString('Lighting: dramatic high-contrast edge rim lighting sculpting the product contours against a moody backdrop.', $generation->creativeProject->prompt);
    }

    public function test_invalid_scene_controls_return_persian_validation_errors(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'محصول تست']);

        $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'surface' => 'invalid_surface_name',
            'props' => 'invalid_props_name',
            'camera_angle' => 'invalid_camera_angle',
            'lighting_setup' => 'invalid_lighting_setup',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'surface' => 'جنس سطح یا پایه انتخاب‌شده نامعتبر است.',
                'props' => 'اکسسوری صحنه انتخاب‌شده نامعتبر است.',
                'camera_angle' => 'زاویه دوربین انتخاب‌شده نامعتبر است.',
                'lighting_setup' => 'نورپردازی انتخاب‌شده نامعتبر است.',
            ]);
    }

    public function test_creative_options_endpoint_returns_scene_controls(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/creative/options')->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'goals',
                'styles',
                'formats',
                'environments',
                'surfaces' => [
                    '*' => ['key', 'label', 'prompt', 'icon'],
                ],
                'props' => [
                    '*' => ['key', 'label', 'prompt', 'icon'],
                ],
                'camera_angles' => [
                    '*' => ['key', 'label', 'prompt', 'icon'],
                ],
                'lighting_setups' => [
                    '*' => ['key', 'label', 'prompt', 'icon'],
                ],
            ],
        ]);
    }
}
