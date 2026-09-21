<?php

namespace Tests\Feature\Products;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_own_products(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $productId = $this->postJson('/api/products', ['name' => 'عطر', 'description' => 'توضیحات'])
            ->assertCreated()->json('data.id');

        $this->getJson('/api/products')->assertOk()->assertJsonPath('data.data.0.name', 'عطر');
        $this->putJson("/api/products/{$productId}", ['name' => 'عطر لوکس'])->assertOk();
        $this->deleteJson("/api/products/{$productId}")->assertOk();
    }

    public function test_user_cannot_access_another_users_product(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = User::factory()->create()->products()->create(['name' => 'Private']);

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_user_can_upload_a_product_image(): void
    {
        Storage::fake('local');
        config(['filesystems.media_disk' => 'local']);
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'کفش']);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/products/{$product->id}/assets", [
            'image' => UploadedFile::fake()->image('shoe.jpg', 1200, 1200)->size(500),
        ])->assertCreated();

        Storage::disk('local')->assertExists($response->json('data.path'));
        Storage::disk('local')->assertExists($response->json('data.thumbnail_path'));
        $this->assertDatabaseHas('product_assets', ['product_id' => $product->id, 'is_primary' => true]);
    }

    public function test_user_can_delete_a_product_asset_and_promote_the_next_asset(): void
    {
        Storage::fake('local');
        config(['filesystems.media_disk' => 'local']);
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'کفش']);
        Sanctum::actingAs($user);

        $first = $this->postJson("/api/products/{$product->id}/assets", [
            'image' => UploadedFile::fake()->image('first.jpg', 1200, 1200),
        ])->assertCreated()->json('data');
        $second = $this->postJson("/api/products/{$product->id}/assets", [
            'image' => UploadedFile::fake()->image('second.jpg', 1200, 1200),
        ])->assertCreated()->json('data');

        $this->deleteJson("/api/products/{$product->id}/assets/{$first['id']}")
            ->assertOk();

        Storage::disk('local')->assertMissing($first['path']);
        $this->assertDatabaseMissing('media_assets', ['id' => $first['id']]);
        $this->assertDatabaseHas('product_assets', [
            'product_id' => $product->id,
            'media_asset_id' => $second['id'],
            'is_primary' => true,
        ]);
    }

    public function test_owner_can_download_a_product_thumbnail(): void
    {
        Storage::fake('local');
        config(['filesystems.media_disk' => 'local']);
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'کفش']);
        Sanctum::actingAs($user);
        $asset = $this->postJson("/api/products/{$product->id}/assets", [
            'image' => UploadedFile::fake()->image('shoe.jpg'),
        ])->assertCreated()->json('data');

        $this->get("/api/products/{$product->id}/assets/{$asset['id']}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp');
    }

    public function test_user_cannot_delete_another_users_product_asset(): void
    {
        Storage::fake('local');
        config(['filesystems.media_disk' => 'local']);
        $owner = User::factory()->create();
        $product = $owner->products()->create(['name' => 'کفش']);
        $asset = $this->actingAs($owner)->postJson("/api/products/{$product->id}/assets", [
            'image' => UploadedFile::fake()->image('shoe.jpg'),
        ])->json('data');

        Sanctum::actingAs(User::factory()->create());
        $this->deleteJson("/api/products/{$product->id}/assets/{$asset['id']}")->assertNotFound();
    }
}
