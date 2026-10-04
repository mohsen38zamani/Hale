<?php

namespace Tests\Feature\Search;

use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvancedSearchTest extends TestCase
{
    use RefreshDatabase;

    private function createGeneration(User $user, string $prompt = 'بطری شیشه‌ای لاکچری', string $productName = 'عطر شب'): Generation
    {
        $product = $user->products()->create(['name' => $productName]);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => ['summary' => 'brief'],
            'prompt' => $prompt,
        ]);

        return Generation::query()->create([
            'user_id' => $user->id,
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'search-'.uniqid()),
        ]);
    }

    public function test_product_search_matches_name_and_description_and_treats_wildcards_literally(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $percent = $user->products()->create(['name' => 'Offer 50%']);
        $user->products()->create(['name' => 'Discount 50 off']);
        $user->products()->create(['name' => 'اسپری خوشبو', 'description' => 'رایحه بهاری ماندگار']);

        $matches = $this->getJson('/api/products?search=50%25')->assertOk()->json('data.data');
        $this->assertCount(1, $matches);
        $this->assertSame($percent->id, $matches[0]['id']);

        $byDescription = $this->getJson('/api/products?search=ماندگار')->assertOk()->json('data.data');
        $this->assertCount(1, $byDescription);
        $this->assertSame('اسپری خوشبو', $byDescription[0]['name']);
    }

    public function test_product_search_is_scoped_to_the_owner(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        User::factory()->create()->products()->create(['name' => 'عطر مخفی دیگران']);
        $user->products()->create(['name' => 'عطر آشکار من']);

        $matches = $this->getJson('/api/products?search=عطر')->assertOk()->json('data.data');
        $this->assertCount(1, $matches);
        $this->assertSame('عطر آشکار من', $matches[0]['name']);
    }

    public function test_generation_search_matches_product_name_and_prompt(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->createGeneration($user);
        $this->createGeneration($user, 'کیف چرمی دست‌دوز', 'کیف اداری');

        $byProduct = $this->getJson('/api/generations?search=عطر شب')->assertOk()->json('data.data');
        $this->assertCount(1, $byProduct);
        $this->assertSame($generation->id, $byProduct[0]['id']);

        $byPrompt = $this->getJson('/api/generations?search=چرمی')->assertOk()->json('data.data');
        $this->assertCount(1, $byPrompt);
        $this->assertNotSame($generation->id, $byPrompt[0]['id']);

        $this->getJson('/api/generations?search='.str_repeat('x', 101))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('search');
    }

    public function test_meilisearch_driver_returns_index_hits_scoped_to_the_user(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $first = $user->products()->create(['name' => 'عطر الف']);
        $second = $user->products()->create(['name' => 'عطر ب']);
        $user->products()->create(['name' => 'عطر ج']);

        config(['search.driver' => 'meilisearch']);
        Http::fake(['*' => Http::response(['hits' => [['id' => $first->id], ['id' => $second->id]]], 200)]);

        $matches = $this->getJson('/api/products?search=عطر')->assertOk()->json('data.data');
        $ids = array_column($matches, 'id');
        sort($ids);
        $expected = [$first->id, $second->id];
        sort($expected);
        $this->assertSame($expected, $ids);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/indexes/products/search')
            && $request['filter'] === 'user_id = '.$user->id);
    }

    public function test_meilisearch_failure_falls_back_to_database_search(): void
    {
        config(['search.driver' => 'meilisearch']);
        Http::fake(['*' => Http::response('unavailable', 503)]);

        Sanctum::actingAs($user = User::factory()->create());
        $user->products()->create(['name' => 'عطر آفلاین']);

        // The fake index hits don't exist in the DB, but a failing service
        // must degrade to LIKE search instead of returning nothing/breaking.
        $matches = $this->getJson('/api/products?search=عطر')->assertOk()->json('data.data');
        $this->assertCount(1, $matches);
        $this->assertSame('عطر آفلاین', $matches[0]['name']);
    }

    public function test_reindex_command_pushes_documents_into_both_indexes(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $user->products()->create(['name' => 'عطر لوکس']);
        $this->createGeneration($user);

        Http::fake(['*' => Http::response(['taskUid' => 1], 202)]);
        $this->artisan('search:reindex')->assertSuccessful();

        Http::assertSent(function ($request): bool {
            $documents = json_decode($request->body(), true);

            return is_array($documents)
                && str_contains($request->url(), '/indexes/products/documents')
                && $request->method() === 'POST'
                && ($documents[0]['name'] ?? null) === 'عطر لوکس';
        });
        Http::assertSent(function ($request): bool {
            $documents = json_decode($request->body(), true);

            return is_array($documents)
                && str_contains($request->url(), '/indexes/generations/documents')
                && str_contains((string) ($documents[0]['prompt'] ?? ''), 'بطری شیشه‌ای لاکچری');
        });
    }

    public function test_reindex_command_survives_a_down_search_service(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $user->products()->create(['name' => 'عطر لوکس']);

        Http::fake(['*' => Http::response('down', 500)]);
        $this->artisan('search:reindex')->assertSuccessful();
    }

    public function test_product_mutations_index_and_unindex_documents(): void
    {
        Http::fake(['*' => Http::response(['taskUid' => 1], 202)]);

        Sanctum::actingAs($user = User::factory()->create());
        $id = $this->postJson('/api/products', ['name' => 'عطر تازه'])->assertCreated()->json('data.id');
        Http::assertSent(function ($request): bool {
            $documents = json_decode($request->body(), true);

            return is_array($documents)
                && str_contains($request->url(), '/indexes/products/documents')
                && ($documents[0]['name'] ?? null) === 'عطر تازه';
        });

        $this->deleteJson("/api/products/{$id}")->assertOk();
        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && str_contains($request->url(), "/indexes/products/documents/{$id}"));
    }

    public function test_generation_search_handles_falsy_string_like_zero(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $p1 = $user->products()->create(['name' => 'مدل 0']);
        $p2 = $user->products()->create(['name' => 'مدل الف']);

        $g1 = $user->generations()->create([
            'creative_project_id' => $user->creativeProjects()->create([
                'product_id' => $p1->id,
                'goal' => 'sales',
                'style' => 'luxury',
                'format' => 'instagram_post',
                'brief' => ['summary' => 'brief'],
                'prompt' => 'prompt zero',
            ])->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'search-zero-1'),
        ]);

        $g2 = $user->generations()->create([
            'creative_project_id' => $user->creativeProjects()->create([
                'product_id' => $p2->id,
                'goal' => 'sales',
                'style' => 'luxury',
                'format' => 'instagram_post',
                'brief' => ['summary' => 'brief'],
                'prompt' => 'prompt alpha',
            ])->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'search-zero-2'),
        ]);

        $response = $this->getJson('/api/generations?search=0')->assertOk();
        $this->assertCount(1, $response->json('data.data'));
        $this->assertSame($g1->id, $response->json('data.data.0.id'));
    }
}
