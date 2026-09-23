<?php

namespace Tests\Feature\Products;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Error-state coverage for the Product Library UI: every failure the frontend
 * can surface (ownership, validation, missing/foreign assets) must map to a
 * well-defined API error envelope with a Persian message.
 */
class ProductLibraryErrorStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['filesystems.media_disk' => 'local']);
    }

    public function test_update_of_another_users_product_returns_404_without_modifying_it(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = User::factory()->create()->products()->create(['name' => 'Private']);

        $this->putJson("/api/products/{$product->id}", ['name' => 'Hacked'])->assertNotFound();
        $this->assertSame('Private', $product->fresh()->name);
    }

    public function test_delete_of_another_users_product_returns_404_and_keeps_row(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = User::factory()->create()->products()->create(['name' => 'Private']);

        $this->deleteJson("/api/products/{$product->id}")->assertNotFound();
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_asset_upload_to_another_users_product_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $product = User::factory()->create()->products()->create(['name' => 'Private']);

        $this->postJson("/api/products/{$product->id}/assets", [
            'image' => UploadedFile::fake()->image('shoe.jpg', 100, 100),
        ])->assertNotFound();
    }

    public function test_update_with_empty_name_returns_persian_validation_error(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $user->products()->create(['name' => 'کفش']);

        $this->putJson("/api/products/{$product->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('message', 'نام محصول نمی‌تواند خالی باشد.');

        $this->assertSame('کفش', $product->fresh()->name);
    }

    public function test_asset_upload_without_image_returns_persian_validation_error(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $user->products()->create(['name' => 'کفش']);

        $this->postJson("/api/products/{$product->id}/assets", [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('message', 'انتخاب و آپلود تصویر محصول الزامی است.');
    }

    public function test_uploading_a_non_image_file_returns_persian_validation_error(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $user->products()->create(['name' => 'کفش']);

        $this->postJson("/api/products/{$product->id}/assets", [
            'image' => UploadedFile::fake()->create('script.php', 100, 'application/x-php'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('message', 'فایل ارسالی باید یک تصویر معتبر باشد.');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_downloading_and_deleting_an_unattached_asset_returns_404(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $user->products()->create(['name' => 'کفش']);
        $asset = $user->mediaAssets()->create([
            'disk' => 'local',
            'path' => 'products/'.$user->id.'/orphan.png',
            'mime' => 'image/png',
            'size' => 123,
        ]);
        Storage::disk('local')->put($asset->path, 'png-data');

        $this->getJson("/api/products/{$product->id}/assets/{$asset->id}/download")->assertNotFound();
        $this->deleteJson("/api/products/{$product->id}/assets/{$asset->id}")->assertNotFound();

        $this->assertDatabaseCount('media_assets', 1);
        Storage::disk('local')->assertExists($asset->path);
    }
}
