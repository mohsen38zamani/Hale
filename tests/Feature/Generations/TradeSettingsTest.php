<?php

namespace Tests\Feature\Generations;

use App\Domains\Creative\Prompts\CategoryPromptBuilder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The trade travels the whole way: product row, generation request, stored
 * project and the brief the prompt was compiled from. A request that names
 * no trade - and a product that owns none - must leave the brief exactly as
 * generic as it was before the trade studios existed.
 */
class TradeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_records_the_trade_and_the_fidelity_pipeline(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'انگشتر نقره']);

        $id = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'professional',
            'format' => 'instagram_post',
            'category' => 'jewelry',
            'fidelity_mode' => 'catalog_white_composite',
        ])->assertStatus(202)->json('data.id');

        $this->getJson("/api/generations/{$id}")->assertOk()
            ->assertJsonPath('data.creative_project.category', 'jewelry')
            ->assertJsonPath('data.creative_project.fidelity_mode', 'catalog_white_composite')
            ->assertJsonPath('data.creative_project.brief.category', 'jewelry')
            ->assertJsonPath('data.creative_project.brief.fidelity_mode', 'catalog_white_composite');
    }

    public function test_an_unknown_trade_is_refused_with_a_persian_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'کیف چرم']);

        $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'professional',
            'format' => 'instagram_post',
            'category' => 'spaceship',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['category'])
            ->assertJsonPath('errors.category.0', 'صنف محصول انتخاب‌شده نامعتبر است.');
    }

    public function test_preview_quotes_the_trade_it_was_asked_for(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'ساعت کلاسیک']);

        $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'goal' => 'branding',
            'category' => 'watches',
            'fidelity_mode' => 'foreground_invariant_inpainting',
        ])->assertOk()
            ->assertJsonPath('data.category', 'watches')
            ->assertJsonPath('data.fidelity_mode', 'foreground_invariant_inpainting')
            ->assertJsonPath('data.brief.category', 'watches')
            ->assertJsonPath('data.brief.fidelity_mode', 'foreground_invariant_inpainting');
    }

    public function test_the_products_own_trade_reaches_the_brief_when_the_form_says_nothing(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'کتانی سفید', 'category' => 'shoes']);

        $id = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'professional',
            'format' => 'instagram_post',
        ])->assertStatus(202)->json('data.id');

        $this->getJson("/api/generations/{$id}")->assertOk()
            ->assertJsonPath('data.creative_project.brief.category', 'shoes')
            // The column records what the studio explicitly chose; the brief
            // records what the prompt was actually built with. Only the
            // latter may fall back to the product.
            ->assertJsonPath('data.creative_project.category', null);
    }

    public function test_a_generation_without_a_trade_keeps_the_brief_free_of_the_keys(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'لیوان سرامیکی']);

        $id = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'professional',
            'format' => 'instagram_post',
        ])->assertStatus(202)->json('data.id');

        $brief = $this->getJson("/api/generations/{$id}")->assertOk()->json('data.creative_project.brief');

        $this->assertArrayNotHasKey('category', $brief, 'A trade-less brief must not carry a trade.');
        $this->assertArrayNotHasKey('fidelity_mode', $brief, 'A trade-less brief must not carry a fidelity pipeline.');
    }

    public function test_product_endpoints_validate_and_persist_the_trade(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/products', ['name' => 'عطر شب', 'category' => 'parfum'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);

        $product = $this->postJson('/api/products', ['name' => 'عطر شب', 'category' => 'perfumes'])
            ->assertCreated()
            ->assertJsonPath('data.category', 'perfumes')
            ->json('data.id');

        $this->patchJson("/api/products/{$product}", ['category' => 'cosmetics'])
            ->assertOk()
            ->assertJsonPath('data.category', 'cosmetics');
    }

    /**
     * Phase 7: every trade, end to end. The copied prompt is the half of the
     * studio that has to survive without the server behind it, so each of the
     * ten trades must reach the text a user copies carrying both clauses the
     * trade owns - the scale lock and the "keep ... unchanged" guardrail -
     * whatever the engine compiled it through.
     */
    public function test_every_trade_reaches_the_copied_prompt_with_its_scale_and_guardrail(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $builder = new CategoryPromptBuilder;

        foreach (array_keys((array) config('creative_categories.categories')) as $trade) {
            $key = (string) $trade;
            $product = $user->products()->create(['name' => "کالای {$key}"]);

            $prompt = $this->postJson('/api/creative/preview', [
                'product_id' => $product->id,
                'goal' => 'sales',
                'category' => $key,
            ])
                ->assertOk()
                ->assertJsonPath('data.category', $key)
                ->json('data.prompt_preview');

            $this->assertIsString($prompt, "Trade [{$key}] produced no prompt at all.");
            $this->assertStringContainsString(
                $builder->scale($key),
                $prompt,
                "The [{$key}] prompt lost its physical scale lock."
            );
            $this->assertStringContainsString(
                $builder->guardrail($key),
                $prompt,
                "The [{$key}] prompt lost the features no model may redraw."
            );
        }
    }
}
