<?php

namespace Tests\Feature\E2E;

use App\Domains\AI\Gateway\AiGateway;
use App\Domains\AI\Services\CircuitBreaker;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Media\Services\WatermarkService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HappyPathFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_happy_path_flow(): void
    {
        Storage::fake('local');
        config([
            'filesystems.media_disk' => 'local',
            'ai.output_disk' => 'local',
            'ai.provider' => 'fake',
            'verification.phone.testing_code' => '123456',
        ]);

        // 1. Register
        $registerRes = $this->postJson('/api/auth/register', [
            'name' => 'سعید زارعی',
            'email' => 'saeed@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ])->assertCreated();

        $token = $registerRes->json('data.token');
        $this->assertNotEmpty($token);

        // Mandatory email verification before generation/checkout.
        $user = User::query()->where('email', 'saeed@example.com')->firstOrFail();
        $this->get(URL::temporarySignedRoute('email.verification.verify', now()->addDay(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]))->assertOk();

        // 2. Login & Profile Check
        $loginRes = $this->postJson('/api/auth/login', [
            'identifier' => 'saeed@example.com',
            'password' => 'secret1234',
        ])->assertOk();

        $user = User::query()->where('email', 'saeed@example.com')->firstOrFail();
        $this->assertSame(0, $loginRes->json('data.user.credits_balance'));

        Sanctum::actingAs($user);

        // 2b. Phone Verification for Free Welcome Credits
        $this->postJson('/api/auth/phone/send-code', ['phone' => '+989120000001'])->assertOk();
        $this->postJson('/api/auth/verify-phone', ['code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.credits_added', 30);

        $profileRes = $this->getJson('/api/user/profile')->assertOk();
        $this->assertSame('free', $profileRes->json('data.plan_key'));
        $this->assertSame(30, $profileRes->json('data.credits_balance'));

        // 3. Create Product & Upload Asset
        $productRes = $this->postJson('/api/products', [
            'name' => 'عطر شکوفه بهار',
            'description' => 'عطر بهاری با رایحه گل‌های تازه و ماندگاری بالا',
        ])->assertCreated();
        $productId = $productRes->json('data.id');

        $assetRes = $this->postJson("/api/products/{$productId}/assets", [
            'image' => UploadedFile::fake()->image('perfume.png', 1024, 1024),
        ])->assertCreated();
        $assetId = $assetRes->json('data.id');
        $this->assertNotEmpty($assetId);

        // 4. Creative Preview (Auto Best)
        $previewRes = $this->postJson('/api/creative/preview', [
            'product_id' => $productId,
            'goal' => 'sales',
            'format' => 'instagram_post',
        ])->assertOk();
        $this->assertSame(10, $previewRes->json('data.estimated_credits'));
        $this->assertNotEmpty($previewRes->json('data.prompt_preview'));

        // 5. Submit Generation Request
        Queue::fake([ProcessGeneration::class]);
        $generateRes = $this->postJson('/api/generations', [
            'product_id' => $productId,
            'goal' => 'sales',
            'format' => 'instagram_post',
            'style' => 'luxury',
        ])->assertStatus(202);

        $generationId = $generateRes->json('data.id');
        $this->assertSame('queued', $generateRes->json('data.status'));

        // Balance should have 10 credits reserved (balance: 20, reserved: 10)
        $this->assertDatabaseHas('credit_accounts', [
            'user_id' => $user->id,
            'balance' => 20,
            'reserved' => 10,
        ]);

        // 6. Process Generation Job
        $job = new ProcessGeneration($generationId);
        $job->handle(
            app(AiGateway::class),
            app(CreditService::class),
            app(WatermarkService::class),
            app(CircuitBreaker::class)
        );

        // 7. Check Generation Result
        $statusRes = $this->getJson("/api/generations/{$generationId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.credits_charged', 10)
            ->assertJsonPath('data.credits_reserved', 0);

        $this->assertNotNull($statusRes->json('data.output_media'));

        // 8. Download Generation Output
        $downloadRes = $this->getJson("/api/generations/{$generationId}/download")
            ->assertOk();
        $this->assertSame('image/png', $downloadRes->headers->get('content-type'));

        // 9. Submit User Feedback
        $this->postJson("/api/generations/{$generationId}/feedback", [
            'feedback' => 'positive',
        ])->assertOk()->assertJsonPath('data.feedback', 'positive');

        // 10. Regenerate
        $regenRes = $this->postJson("/api/generations/{$generationId}/regenerate")
            ->assertStatus(202);
        $newGenId = $regenRes->json('data.id');
        $this->assertNotEquals($generationId, $newGenId);
        $this->assertSame('queued', $regenRes->json('data.status'));
    }
}
