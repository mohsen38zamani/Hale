<?php

namespace Tests\Feature\Creative;

use App\Domains\Credits\Models\CreditTransaction;
use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DualTrackHappyPathE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    public function test_complete_dual_track_happy_path_journey(): void
    {
        // 1. Setup User with initial credits (50 credits)
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 50);
        Sanctum::actingAs($user);

        // 2. User creates a product
        $product = $user->products()->create([
            'name' => 'عطر لوکس فرانسوی',
            'category' => 'perfumes',
        ]);

        // -------------------------------------------------------------
        // TRACK 1: In-App Generation Workflow
        // -------------------------------------------------------------
        // Check estimate for Track 1
        $estimateRes = $this->postJson('/api/credits/estimate', [
            'type' => 'image',
            'quality' => 'standard',
            'prompt_type' => 'image',
            'has_trade' => true,
        ]);
        $estimateRes->assertOk()
            ->assertJsonPath('data.cost', 10)
            ->assertJsonPath('data.prompt_cost', 2)
            ->assertJsonPath('data.sufficient', true);

        // Execute In-App Generation
        $genRes = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'natural',
            'format' => 'instagram_post',
            'quality' => 'standard',
            'target_ai' => 'imagen',
            'category' => 'perfumes',
        ]);
        $genRes->assertStatus(202);
        $generationId = $genRes->json('data.id');
        $this->assertNotNull($generationId);

        // Assert 10 credits reserved/deducted
        $this->assertSame(40, $credits->account($user)->fresh()->balance);

        // Assert Transaction recorded with delivery_path = in_app_generation
        $genTx = CreditTransaction::where('user_id', $user->id)
            ->where('delivery_path', 'in_app_generation')
            ->first();
        $this->assertNotNull($genTx);
        $this->assertSame(-10, $genTx->amount);
        $this->assertSame('in_app_generation', $genTx->delivery_path);

        // -------------------------------------------------------------
        // TRACK 2: Professional Prompt-Only Compilation Workflow
        // -------------------------------------------------------------
        // User selects Midjourney (external target)
        $idempotencyKey = 'dual-track-test-key-'.uuid_create();
        $promptRes = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/creative/compile-prompt', [
                'product_id' => $product->id,
                'target_ai' => 'midjourney',
                'goal' => 'sales',
                'style' => 'luxury',
                'format' => 'instagram_post',
                'category' => 'perfumes',
            ]);

        $promptRes->assertOk()
            ->assertJsonPath('data.target_ai', 'midjourney')
            ->assertJsonPath('data.settings.category', 'perfumes')
            ->assertJsonPath('data.credits_charged', 2)
            ->assertJsonPath('data.new_balance', 38);

        $compiledPrompt = $promptRes->json('data.prompt');
        $this->assertNotEmpty($compiledPrompt);
        $this->assertStringContainsString('--ar 1:1', $compiledPrompt);

        // Assert 2 credits deducted atomically
        $this->assertSame(38, $credits->account($user)->fresh()->balance);

        // Assert Prompt Transaction recorded in ledger with unit economics metadata
        $promptTx = CreditTransaction::where('user_id', $user->id)
            ->where('type', 'prompt_compilation')
            ->first();
        $this->assertNotNull($promptTx);
        $this->assertSame(-2, $promptTx->amount);
        $this->assertSame('prompt_only', $promptTx->delivery_path);
        $this->assertEquals(0.0006, (float) $promptTx->internal_cost_usd);

        // Double-spend test with identical Idempotency-Key
        $dupRes = $this->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/creative/compile-prompt', [
                'product_id' => $product->id,
                'target_ai' => 'midjourney',
            ]);
        $dupRes->assertOk();
        $this->assertSame(38, $credits->account($user)->fresh()->balance, 'Idempotency key must prevent double debiting credits.');

        // -------------------------------------------------------------
        // Admin Unit Economics Verification
        // -------------------------------------------------------------
        config(['auth.admin_emails' => 'admin@hale.test']);
        $admin = User::factory()->create(['email' => 'admin@hale.test']);
        Sanctum::actingAs($admin);

        $metricsRes = $this->getJson('/api/admin/metrics');
        $metricsRes->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'kpis',
                    'unit_economics' => [
                        'in_app_generation',
                        'prompt_only',
                        'blended',
                    ],
                ],
            ]);

        $this->assertSame(1, $metricsRes->json('data.overview.total_prompts_compiled'));
    }
}
