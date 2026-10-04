<?php

namespace Tests\Feature\Generations;

use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CaptionGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_caption_uses_the_text_model_and_charges_credits(): void
    {
        config(['ai.providers.google.api_key' => 'test-key']);
        Http::fake([
            '*generativelanguage.googleapis.com*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode([
                    'caption' => 'کپشن آزمایشی',
                    'hashtags' => ['#تست', 'برند'],
                ], JSON_UNESCAPED_UNICODE)]]]]],
            ], 200),
        ]);
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->makeGeneration($user);

        $this->postJson("/api/generations/{$generation->id}/caption", ['language' => 'fa', 'tone' => 'friendly'])
            ->assertOk()
            ->assertJsonPath('data.source', 'ai')
            ->assertJsonPath('data.caption', 'کپشن آزمایشی')
            ->assertJsonPath('data.cached', false)
            ->assertJsonPath('data.cost', 3)
            ->assertJsonPath('data.hashtags.0', '#تست')
            ->assertJsonPath('data.hashtags.1', '#برند');

        $this->assertSame(config('credits.initial_balance') - 3, app(CreditService::class)->account($user)->fresh()->balance);
        $generation->refresh();
        $this->assertSame('ai', $generation->metadata['caption']['source']);
        $this->assertSame('fa', $generation->metadata['caption']['language']);
    }

    public function test_caption_falls_back_to_template_without_ai_key(): void
    {
        config(['ai.providers.google.api_key' => '']);
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->makeGeneration($user);

        $response = $this->postJson("/api/generations/{$generation->id}/caption")
            ->assertOk()
            ->assertJsonPath('data.source', 'fallback')
            ->assertJsonPath('data.cost', 3);

        $caption = $response->json('data.caption');
        $this->assertStringContainsString('عطر شب', $caption);
        $this->assertNotEmpty($response->json('data.hashtags'));
        $this->assertCount(3, $response->json('data.hashtags'));
    }

    public function test_caption_falls_back_when_the_text_model_errors(): void
    {
        config(['ai.providers.google.api_key' => 'test-key']);
        Http::fake(['*' => Http::response(['error' => ['message' => 'boom']], 500)]);
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->makeGeneration($user);

        $this->postJson("/api/generations/{$generation->id}/caption", ['language' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.source', 'fallback');
    }

    public function test_caption_requires_credits(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        app(CreditService::class)->account($user)->forceFill(['balance' => 1])->save();
        $generation = $this->makeGeneration($user);

        $this->postJson("/api/generations/{$generation->id}/caption")
            ->assertStatus(402)
            ->assertJsonPath('error.code', 'INSUFFICIENT_CREDITS');

        $this->assertSame(0, app(CreditService::class)->account($user)->transactions()->where('type', 'text_task')->count());
    }

    public function test_caption_rejects_foreign_generations(): void
    {
        Sanctum::actingAs($owner = User::factory()->create());
        $generation = $this->makeGeneration($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/generations/{$generation->id}/caption")->assertNotFound();
    }

    public function test_caption_moderates_user_supplied_content(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->makeGeneration($user, 'پورن تست');

        $this->postJson("/api/generations/{$generation->id}/caption")->assertStatus(422);
        $this->assertSame(config('credits.initial_balance'), app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_same_language_and_tone_returns_cached_caption_for_free(): void
    {
        config(['ai.providers.google.api_key' => '']);
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->makeGeneration($user);

        $this->postJson("/api/generations/{$generation->id}/caption")
            ->assertOk()
            ->assertJsonPath('data.cost', 3);

        $this->postJson("/api/generations/{$generation->id}/caption")
            ->assertOk()
            ->assertJsonPath('data.cached', true)
            ->assertJsonPath('data.cost', 0);

        $this->assertSame(config('credits.initial_balance') - 3, app(CreditService::class)->account($user)->fresh()->balance);

        // A different tone is a new caption and charges again.
        $this->postJson("/api/generations/{$generation->id}/caption", ['tone' => 'formal'])
            ->assertOk()
            ->assertJsonPath('data.cost', 3);

        // Refresh forces a rewrite of the cached combination.
        $this->postJson("/api/generations/{$generation->id}/caption", ['refresh' => true])
            ->assertOk()
            ->assertJsonPath('data.cost', 3);

        $this->assertSame(config('credits.initial_balance') - 9, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_caption_validates_language_and_tone(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->makeGeneration($user);

        $this->postJson("/api/generations/{$generation->id}/caption", ['language' => 'de'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_estimate_endpoint_supports_text_tasks(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/credits/estimate', ['type' => 'text'])
            ->assertOk()
            ->assertJsonPath('data.cost', 3);
    }

    private function makeGeneration(User $user, string $productName = 'عطر شب'): Generation
    {
        $product = $user->products()->create(['name' => $productName, 'description' => 'توضیح آزمایشی']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'prompt',
        ]);

        return $project->generations()->create([
            'user_id' => $user->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'prompt'),
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);
    }
}
