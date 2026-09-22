<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorageSecurityAndPathTraversalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['filesystems.media_disk' => 'local']);
    }

    public function test_user_cannot_download_another_users_generation_output(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $project = $owner->creativeProjects()->create([
            'goal' => 'sales',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'brief' => ['title' => 'Test'],
            'prompt' => 'A photo',
            'product_id' => $owner->products()->create(['name' => 'Product 1'])->id,
            'aspect_ratio' => '1:1',
        ]);

        $filePath = 'generations/'.$owner->id.'/output.png';
        Storage::disk('local')->put($filePath, 'fake-image-binary-data');

        $media = $owner->mediaAssets()->create([
            'disk' => 'local',
            'path' => $filePath,
            'mime' => 'image/png',
            'size' => 1234,
        ]);

        $generation = $project->generations()->create([
            'user_id' => $owner->id,
            'type' => 'image',
            'status' => 'completed',
            'output_media_id' => $media->id,
            'prompt_hash' => hash('sha256', 'A photo'),
        ]);

        // Owner can download
        Sanctum::actingAs($owner);
        $this->get("/api/generations/{$generation->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // Attacker is rejected with 404
        Sanctum::actingAs($attacker);
        $this->get("/api/generations/{$generation->id}/download")
            ->assertNotFound();
    }

    public function test_user_cannot_download_another_users_product_asset(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $ownerProduct = $owner->products()->create(['name' => 'Owner Product']);
        $attackerProduct = $attacker->products()->create(['name' => 'Attacker Product']);

        $filePath = "users/{$owner->id}/products/secret.png";
        Storage::disk('local')->put($filePath, 'owner-image-bytes');

        $asset = $owner->mediaAssets()->create([
            'disk' => 'local',
            'path' => $filePath,
            'mime' => 'image/png',
            'size' => 500,
        ]);
        $ownerProduct->assets()->attach($asset->id, ['is_primary' => true]);

        // Attacker attempting to access via owner product ID -> 404
        Sanctum::actingAs($attacker);
        $this->get("/api/products/{$ownerProduct->id}/assets/{$asset->id}/download")
            ->assertNotFound();

        // Attacker attempting cross-attach via attacker product ID -> 404
        $this->get("/api/products/{$attackerProduct->id}/assets/{$asset->id}/download")
            ->assertNotFound();
    }

    public function test_invalid_mimes_and_php_scripts_are_rejected_on_upload(): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'Test Product']);
        Sanctum::actingAs($user);

        // Attempting to upload a PHP script pretending to be image
        $maliciousFile = UploadedFile::fake()->create('exploit.php', 10, 'application/x-php');
        $response = $this->postJson("/api/products/{$product->id}/assets", [
            'image' => $maliciousFile,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        // Attempting to upload executable shell script
        $shFile = UploadedFile::fake()->create('script.sh', 10, 'application/x-sh');
        $response = $this->postJson("/api/products/{$product->id}/assets", [
            'image' => $shFile,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        // Attempting to upload PDF
        $pdfFile = UploadedFile::fake()->create('document.pdf', 10, 'application/pdf');
        $response = $this->postJson("/api/products/{$product->id}/assets", [
            'image' => $pdfFile,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_attacker_cannot_act_on_another_users_generation(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $project = $owner->creativeProjects()->create([
            'goal' => 'sales',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'brief' => ['title' => 'Test'],
            'prompt' => 'A prompt',
            'product_id' => $owner->products()->create(['name' => 'Owner Product'])->id,
            'aspect_ratio' => '1:1',
        ]);

        $generation = $project->generations()->create([
            'user_id' => $owner->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'A prompt'),
        ]);

        Sanctum::actingAs($attacker);

        // Feedback
        $this->postJson("/api/generations/{$generation->id}/feedback", ['feedback' => 'positive'])
            ->assertNotFound();

        // Retry
        $this->postJson("/api/generations/{$generation->id}/retry")
            ->assertNotFound();

        // Regenerate
        $this->postJson("/api/generations/{$generation->id}/regenerate")
            ->assertNotFound();

        // Show
        $this->getJson("/api/generations/{$generation->id}")
            ->assertNotFound();
    }
}
