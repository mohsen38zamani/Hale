<?php

namespace Tests\Feature\Performance;

use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceQueryAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_endpoint_does_not_suffer_from_n_plus_one(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create 10 products, each with a primary asset
        for ($i = 0; $i < 10; $i++) {
            $product = $user->products()->create([
                'name' => "Product {$i}",
                'description' => 'Test product description',
            ]);

            $asset = $user->mediaAssets()->create([
                'disk' => 'local',
                'path' => "assets/prod_{$i}.jpg",
                'mime' => 'image/jpeg',
                'size' => 1024,
            ]);

            $product->assets()->attach($asset->id, ['is_primary' => true]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $queries = DB::getQueryLog();

        // 1 query to count products + 1 query to fetch page items + 1 query to fetch eager loaded assets
        $this->assertLessThanOrEqual(5, count($queries), 'Products endpoint has excessive or N+1 queries');
    }

    public function test_generations_endpoint_eager_loads_relations_without_n_plus_one(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $user->products()->create([
            'name' => 'Flagship Phone',
            'description' => 'Tech product',
        ]);

        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'prompt' => 'Launch Campaign',
            'brief' => ['overview' => 'Test campaign brief'],
        ]);

        for ($i = 0; $i < 8; $i++) {
            Generation::create([
                'user_id' => $user->id,
                'creative_project_id' => $project->id,
                'type' => 'image',
                'status' => 'completed',
                'prompt_hash' => hash('sha256', "prompt {$i}"),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/generations');

        $response->assertOk();
        $queries = DB::getQueryLog();

        // Count + Select generations + CreativeProjects + Products + OutputMedia
        $this->assertLessThanOrEqual(6, count($queries), 'Generations endpoint has excessive or N+1 queries');
    }

    public function test_notifications_endpoint_queries_are_bounded(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 10; $i++) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\SystemAlert',
                'data' => ['message' => "Alert #{$i}"],
                'read_at' => $i % 2 === 0 ? now() : null,
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/notifications');

        $response->assertOk();
        $queries = DB::getQueryLog();

        // Count total + Select page + Count unread
        $this->assertLessThanOrEqual(4, count($queries), 'Notifications endpoint has excessive queries');
    }

    public function test_user_profile_endpoint_queries_are_bounded(): void
    {
        $user = User::factory()->create();
        app(CreditService::class)->account($user);
        Sanctum::actingAs($user);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson('/api/user/profile');

        $response->assertOk();
        $queries = DB::getQueryLog();

        $this->assertLessThanOrEqual(8, count($queries), 'User profile endpoint has excessive queries');
    }
}
