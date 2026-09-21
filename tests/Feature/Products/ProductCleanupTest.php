<?php

namespace Tests\Feature\Products;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_product_cleans_up_orphaned_assets_and_storage_files(): void
    {
        Storage::fake('local');
        config(['filesystems.media_disk' => 'local']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $user->products()->create(['name' => 'کیف چرم']);

        $assetData = $this->postJson("/api/products/{$product->id}/assets", [
            'image' => UploadedFile::fake()->image('bag.jpg', 800, 800),
        ])->assertCreated()->json('data');

        $path = $assetData['path'];
        $thumbPath = $assetData['thumbnail_path'];

        Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertExists($thumbPath);

        // Delete the product
        $this->deleteJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.message', 'محصول حذف شد.');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('media_assets', ['id' => $assetData['id']]);
        Storage::disk('local')->assertMissing($path);
        Storage::disk('local')->assertMissing($thumbPath);
    }

    public function test_deleting_product_preserves_asset_if_linked_to_another_product(): void
    {
        Storage::fake('local');
        config(['filesystems.media_disk' => 'local']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product1 = $user->products()->create(['name' => 'محصول ۱']);
        $product2 = $user->products()->create(['name' => 'محصول ۲']);

        $assetData = $this->postJson("/api/products/{$product1->id}/assets", [
            'image' => UploadedFile::fake()->image('shared.jpg', 800, 800),
        ])->assertCreated()->json('data');

        // Link the same media asset to product2 as well
        $product2->assets()->attach($assetData['id'], ['is_primary' => true]);

        // Delete product1
        $this->deleteJson("/api/products/{$product1->id}")->assertOk();

        // Asset should STILL exist because product2 is using it
        $this->assertDatabaseMissing('products', ['id' => $product1->id]);
        $this->assertDatabaseHas('products', ['id' => $product2->id]);
        $this->assertDatabaseHas('media_assets', ['id' => $assetData['id']]);
        Storage::disk('local')->assertExists($assetData['path']);
    }
}
