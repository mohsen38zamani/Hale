<?php

namespace Tests\Feature\Admin;

use App\Domains\AI\Gateway\AiGateway;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Generations\Models\Generation;
use App\Domains\Notifications\Notifications\GenerationStatusNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCancelGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['auth.admin_emails' => 'admin@hale.test']);
        $this->admin = User::factory()->create(['email' => 'admin@hale.test']);
    }

    /**
     * @return array{0: User, 1: Generation}
     */
    private function createUserWithGeneration(string $status, ?int $reserve = null, ?string $lease = null): array
    {
        $credits = app(CreditService::class);
        $user = User::factory()->create();
        $credits->initialize($user, 100);

        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'prompt',
        ]);
        $generation = Generation::query()->create([
            'user_id' => $user->id,
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => $status,
            'prompt_hash' => hash('sha256', 'prompt'),
            'metadata' => ['aspect_ratio' => '1:1'],
            'processing_lease_expires_at' => $lease,
        ]);

        if ($reserve !== null) {
            $credits->reserve($user, $generation, $reserve);
        }

        return [$user, $generation];
    }

    public function test_non_admin_cannot_cancel_a_generation(): void
    {
        [, $generation] = $this->createUserWithGeneration('queued');
        $regular = User::factory()->create(['email' => 'regular@user.test']);
        Sanctum::actingAs($regular);

        $this->postJson("/api/admin/generations/{$generation->id}/cancel")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->assertSame('queued', $generation->fresh()->status);
    }

    public function test_cancel_queued_generation_refunds_reserved_credits_and_notifies(): void
    {
        Notification::fake();
        [$user, $generation] = $this->createUserWithGeneration('queued', reserve: 10);
        $credits = app(CreditService::class);

        $this->assertSame(90, $credits->account($user)->balance);
        $this->assertSame(10, $credits->account($user)->reserved);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/generations/{$generation->id}/cancel", ['reason' => 'تقاضای کاربر'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.error_message', 'تقاضای کاربر');

        $fresh = $generation->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame(0, $fresh->credits_reserved);
        $this->assertSame(0, $fresh->credits_charged);
        $this->assertNull($fresh->processing_lease_expires_at);

        $this->assertSame(100, $credits->account($user)->fresh()->balance);
        $this->assertSame(0, $credits->account($user)->fresh()->reserved);

        $this->assertDatabaseHas('credit_transactions', [
            'generation_id' => $generation->id,
            'type' => 'refund',
            'amount' => 10,
        ]);

        Notification::assertSentTo($user, GenerationStatusNotification::class);
    }

    public function test_cancel_processing_generation_releases_lease_and_refunds(): void
    {
        [$user, $generation] = $this->createUserWithGeneration(
            'processing',
            reserve: 10,
            lease: now()->addMinutes(5)->toDateTimeString(),
        );
        $credits = app(CreditService::class);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/generations/{$generation->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $fresh = $generation->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNull($fresh->processing_lease_expires_at);
        $this->assertSame(100, $credits->account($user)->fresh()->balance);
        $this->assertSame(0, $credits->account($user)->fresh()->reserved);
    }

    public function test_completed_generation_cannot_be_cancelled(): void
    {
        [, $generation] = $this->createUserWithGeneration('completed');
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/generations/{$generation->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'GENERATION_NOT_CANCELLABLE');

        $this->assertSame('completed', $generation->fresh()->status);
    }

    public function test_cancelling_twice_is_rejected(): void
    {
        [, $generation] = $this->createUserWithGeneration('queued', reserve: 10);
        Sanctum::actingAs($this->admin);

        $this->postJson("/api/admin/generations/{$generation->id}/cancel")->assertOk();

        $this->postJson("/api/admin/generations/{$generation->id}/cancel")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'GENERATION_NOT_CANCELLABLE');
    }

    public function test_cancelled_generation_is_not_processed_by_the_queue_job(): void
    {
        [$user, $generation] = $this->createUserWithGeneration('queued', reserve: 10);
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/generations/{$generation->id}/cancel")->assertOk();
        $this->app['auth']->forgetGuards();

        $gateway = $this->mock(AiGateway::class);
        $gateway->shouldNotReceive('generate');

        (new ProcessGeneration($generation->id))->handle($gateway, app(CreditService::class));

        $fresh = $generation->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame(0, $fresh->credits_charged);
        $this->assertSame(100, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_cancelled_generation_cannot_be_retried_by_the_owner(): void
    {
        [$owner, $generation] = $this->createUserWithGeneration('queued');
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/generations/{$generation->id}/cancel")->assertOk();

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($owner);

        $this->postJson("/api/generations/{$generation->id}/retry")->assertStatus(409);
    }
}
