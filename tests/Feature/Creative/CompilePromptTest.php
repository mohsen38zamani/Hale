<?php

namespace Tests\Feature\Creative;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Credits\Models\CreditTransaction;
use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompilePromptTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_purchase_and_compile_trade_prompt_with_credit_deduction(): void
    {
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 30);
        $product = $user->products()->create([
            'name' => 'انگشتر برلیان',
            'category' => 'jewelry',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/creative/compile-prompt', [
            'product_id' => $product->id,
            'format' => CreativeFormat::InstagramPost->value,
            'category' => 'jewelry',
            'target_ai' => 'midjourney',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.credits_charged', 2)
            ->assertJsonPath('data.new_balance', 28)
            ->assertJsonPath('data.target_ai', 'midjourney');

        $prompt = (string) $response->json('data.prompt');
        $this->assertStringContainsString('انگشتر برلیان', $prompt);
        $this->assertStringContainsString('--ar 1:1', $prompt);

        // Transaction recorded with delivery_path = prompt_only
        $transaction = CreditTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'prompt_compilation')
            ->firstOrFail();

        $this->assertSame(-2, $transaction->amount);
        $this->assertSame(28, $transaction->balance_after);
        $this->assertSame('prompt_only', $transaction->metadata['delivery_path'] ?? null);
        $this->assertSame('jewelry', $transaction->metadata['category'] ?? null);
    }

    public function test_user_can_purchase_video_direction_prompt_charging_five_credits(): void
    {
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 30);
        $product = $user->products()->create([
            'name' => 'ساعت هوشمند',
            'category' => 'watches',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/creative/compile-prompt', [
            'product_id' => $product->id,
            'format' => CreativeFormat::InstagramReel->value,
            'category' => 'watches',
            'target_ai' => 'runway',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.credits_charged', 5)
            ->assertJsonPath('data.new_balance', 25)
            ->assertJsonPath('data.type', 'video');

        $this->assertSame(25, $credits->account($user)->fresh()->balance);
    }

    public function test_user_with_insufficient_credits_cannot_compile_prompt(): void
    {
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 1); // 1 credit is not enough for 2-credit trade prompt
        $product = $user->products()->create([
            'name' => 'کفش ورزشی',
            'category' => 'shoes',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/creative/compile-prompt', [
            'product_id' => $product->id,
            'format' => CreativeFormat::InstagramPost->value,
            'category' => 'shoes',
            'target_ai' => 'flux',
        ]);

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INSUFFICIENT_CREDITS');

        $this->assertSame(1, $credits->account($user)->fresh()->balance);
    }

    public function test_idempotency_prevents_double_spending_for_same_key(): void
    {
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 20);
        $product = $user->products()->create([
            'name' => 'ادکلن فرانسوی',
            'category' => 'perfumes',
        ]);
        Sanctum::actingAs($user);

        $idempotencyKey = 'prompt-unique-key-12345';

        // First attempt: charges 2 credits -> balance 18
        $first = $this->withHeader('Idempotency-Key', $idempotencyKey)->postJson('/api/creative/compile-prompt', [
            'product_id' => $product->id,
            'format' => CreativeFormat::InstagramPost->value,
            'category' => 'perfumes',
            'target_ai' => 'claude',
        ]);
        $first->assertOk()->assertJsonPath('data.new_balance', 18);

        // Second attempt with exact same key: replayed without double charge
        $second = $this->withHeader('Idempotency-Key', $idempotencyKey)->postJson('/api/creative/compile-prompt', [
            'product_id' => $product->id,
            'format' => CreativeFormat::InstagramPost->value,
            'category' => 'perfumes',
            'target_ai' => 'claude',
        ]);
        $second->assertOk()->assertJsonPath('data.new_balance', 18);

        $this->assertSame(18, $credits->account($user)->fresh()->balance);
        $this->assertSame(1, CreditTransaction::query()->where('user_id', $user->id)->where('type', 'prompt_compilation')->count());
    }

    public function test_user_cannot_compile_prompt_for_another_users_product(): void
    {
        $owner = User::factory()->create();
        $product = $owner->products()->create(['name' => 'Private Product']);

        $attacker = User::factory()->create();
        Sanctum::actingAs($attacker);

        $this->postJson('/api/creative/compile-prompt', [
            'product_id' => $product->id,
            'format' => CreativeFormat::InstagramPost->value,
        ])->assertNotFound();
    }

    public function test_validation_rejects_invalid_category_or_target_ai(): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'Test']);
        Sanctum::actingAs($user);

        $this->postJson('/api/creative/compile-prompt', [
            'product_id' => $product->id,
            'category' => 'invalid_trade_key',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['category']);

        $this->postJson('/api/creative/compile-prompt', [
            'product_id' => $product->id,
            'target_ai' => 'non_existent_ai',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['target_ai']);
    }
}
