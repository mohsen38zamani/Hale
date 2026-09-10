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
}
