<?php

namespace Tests\Feature\Generations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenerationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_preview_auto_best_and_queue_generation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);
        $this->postJson('/api/creative/preview', ['product_id' => $product->id, 'goal' => 'sales'])->assertOk()->assertJsonPath('data.goal', 'sales');
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
}
