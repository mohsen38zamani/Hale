<?php

namespace Tests\Feature\Favorites;

use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FavoriteHistoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private function createGeneration(User $user, string $status = 'completed', string $type = 'image'): Generation
    {
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => ['summary' => 'brief'],
            'prompt' => 'prompt',
        ]);

        return Generation::query()->create([
            'user_id' => $user->id,
            'creative_project_id' => $project->id,
            'type' => $type,
            'status' => $status,
            'prompt_hash' => hash('sha256', 'fav-'.uniqid()),
        ]);
    }

    public function test_user_can_toggle_a_product_favorite(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $user->products()->create(['name' => 'عطر لوکس']);

        $this->postJson('/api/favorites/toggle', ['type' => 'product', 'id' => $product->id])
            ->assertOk()
            ->assertJsonPath('data.favorited', true);
        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'favoritable_type' => 'App\Domains\Products\Models\Product',
            'favoritable_id' => $product->id,
        ]);

        $this->postJson('/api/favorites/toggle', ['type' => 'product', 'id' => $product->id])
            ->assertOk()
            ->assertJsonPath('data.favorited', false);
        $this->assertDatabaseMissing('favorites', ['favoritable_id' => $product->id]);
    }

    public function test_user_can_toggle_a_generation_favorite_and_list_it(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->createGeneration($user);

        $this->postJson('/api/favorites/toggle', ['type' => 'generation', 'id' => $generation->id])
            ->assertOk()
            ->assertJsonPath('data.favorited', true);

        $this->getJson('/api/favorites?type=generation')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $generation->id)
            ->assertJsonPath('data.data.0.is_favorite', true);
    }

    public function test_favorites_are_isolated_between_users(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $foreignProduct = User::factory()->create()->products()->create(['name' => 'Private']);
        $foreignGeneration = $this->createGeneration(User::factory()->create());

        $this->postJson('/api/favorites/toggle', ['type' => 'product', 'id' => $foreignProduct->id])->assertNotFound();
        $this->postJson('/api/favorites/toggle', ['type' => 'generation', 'id' => $foreignGeneration->id])->assertNotFound();
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_favorites_require_authentication_and_valid_type(): void
    {
        $this->getJson('/api/favorites?type=product')->assertUnauthorized();
        $this->postJson('/api/favorites/toggle', ['type' => 'product', 'id' => 1])->assertUnauthorized();

        Sanctum::actingAs($user = User::factory()->create());
        $product = $user->products()->create(['name' => 'عطر']);
        $this->postJson('/api/favorites/toggle', ['type' => 'collection', 'id' => $product->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_products_index_supports_favorite_flag_and_only_filter(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $loved = $user->products()->create(['name' => 'عطر']);
        $plain = $user->products()->create(['name' => 'کفش']);
        $this->postJson('/api/favorites/toggle', ['type' => 'product', 'id' => $loved->id])->assertOk();

        $all = $this->getJson('/api/products')->assertOk()->json('data.data');
        $this->assertCount(2, $all);
        $flags = collect($all)->mapWithKeys(fn ($item) => [$item['id'] => $item['is_favorite']]);
        $this->assertTrue((bool) $flags[$loved->id]);
        $this->assertFalse((bool) $flags[$plain->id]);

        $only = $this->getJson('/api/products?favorite=1')->assertOk()->json('data.data');
        $this->assertCount(1, $only);
        $this->assertSame($loved->id, $only[0]['id']);
    }

    public function test_generations_index_supports_status_and_favorite_filters(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $done = $this->createGeneration($user, 'completed');
        $failed = $this->createGeneration($user, 'failed', 'video');
        $this->postJson('/api/favorites/toggle', ['type' => 'generation', 'id' => $done->id])->assertOk();

        $failedList = $this->getJson('/api/generations?status=failed')->assertOk()->json('data.data');
        $this->assertCount(1, $failedList);
        $this->assertSame($failed->id, $failedList[0]['id']);
        $this->assertFalse((bool) $failedList[0]['is_favorite']);

        $favorites = $this->getJson('/api/generations?favorite=1')->assertOk()->json('data.data');
        $this->assertCount(1, $favorites);
        $this->assertSame($done->id, $favorites[0]['id']);
        $this->assertTrue((bool) $favorites[0]['is_favorite']);

        $combined = $this->getJson('/api/generations?type=image&status=completed&favorite=1')->assertOk()->json('data.data');
        $this->assertCount(1, $combined);

        $this->getJson('/api/generations?status=archived')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_deleting_a_product_cleans_up_its_favorite(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $user->products()->create(['name' => 'عطر']);
        $this->postJson('/api/favorites/toggle', ['type' => 'product', 'id' => $product->id])->assertOk();

        $this->deleteJson("/api/products/{$product->id}")->assertOk();

        $this->assertDatabaseMissing('favorites', ['favoritable_id' => $product->id]);
        $this->getJson('/api/favorites?type=product')->assertOk()->assertJsonPath('data.data', []);
    }

    public function test_dashboard_exposes_favorite_and_history_filter_controls(): void
    {
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('data-products-favorite-filter', false);
        $response->assertSee('data-history-filters', false);
        $response->assertSee('data-history-type', false);
        $response->assertSee('data-history-status', false);
        $response->assertSee('data-history-from', false);
        $response->assertSee('data-history-to', false);
        $response->assertSee('data-history-favorite', false);
        $response->assertSee('data-history-reset', false);
    }
}
