<?php

namespace Tests\Feature\Generations;

use App\Domains\AI\Gateway\AiGateway;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Throwable;
use Tests\TestCase;

class ProcessGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_is_dispatched_and_pipeline_tracks_output_and_cost(): void
    {
        Queue::fake();
        Storage::fake('local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);

        $id = $this->postJson('/api/generations', ['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post'])->assertAccepted()->json('data.id');
        Queue::assertPushed(ProcessGeneration::class, fn (ProcessGeneration $job) => $job->generationId === $id);

        (new ProcessGeneration($id))->handle(app(AiGateway::class), app(CreditService::class));
        $generation = $user->generations()->findOrFail($id);

        $this->assertSame('completed', $generation->status);
        $this->assertSame('local', $generation->provider);
        $this->assertCount(1, $generation->usageLogs);
        $this->assertSame(10, $generation->credits_charged);
        $this->assertTrue(Storage::disk('local')->exists($generation->outputMedia->path));
    }

    public function test_failed_generation_can_be_retried_after_refund(): void
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);
        $generation = $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'failed', 'prompt_hash' => hash('sha256', 'prompt')]);
        $credits = app(CreditService::class);

        $credits->reserve($user, $generation, 10);
        $credits->refund($generation);
        $credits->reserve($user, $generation, 10);

        $this->assertSame(10, $generation->fresh()->credits_reserved);
        $this->assertSame(config('credits.initial_balance') - 10, $credits->account($user)->fresh()->balance);
    }

    public function test_transient_failure_keeps_reservation_and_successful_retry_settles_once(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $generation = $this->createGeneration($user);
        $credits = app(CreditService::class);
        $credits->reserve($user, $generation, 10);

        $temporaryFailure = new RuntimeException('temporary provider failure');
        $failingGateway = \Mockery::mock(AiGateway::class);
        $failingGateway->shouldReceive('generate')->once()->andThrow($temporaryFailure);

        try {
            (new ProcessGeneration($generation->id))->handle($failingGateway, $credits);
        } catch (Throwable $exception) {
            $this->assertSame($temporaryFailure, $exception);
        }

        $this->assertSame('queued', $generation->fresh()->status);
        $this->assertSame(10, $generation->fresh()->credits_reserved);
        $this->assertSame(config('credits.initial_balance') - 10, $credits->account($user)->fresh()->balance);

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), $credits);

        $generation->refresh();
        $this->assertSame('completed', $generation->status);
        $this->assertSame(0, $generation->credits_reserved);
        $this->assertSame(10, $generation->credits_charged);
        $this->assertSame(2, $generation->user->creditAccount->transactions()->count());
    }

    public function test_terminal_failure_refunds_reserved_credits(): void
    {
        $user = User::factory()->create();
        $generation = $this->createGeneration($user);
        $credits = app(CreditService::class);
        $credits->reserve($user, $generation, 10);
        $terminalFailure = new RuntimeException('permanent provider failure');
        $job = new ProcessGeneration($generation->id);
        $job->tries = 1;
        $failingGateway = \Mockery::mock(AiGateway::class);
        $failingGateway->shouldReceive('generate')->once()->andThrow($terminalFailure);

        try {
            $job->handle($failingGateway, $credits);
        } catch (Throwable $exception) {
            $this->assertSame($terminalFailure, $exception);
        }

        $job->failed($terminalFailure);

        $this->assertSame('failed', $generation->fresh()->status);
        $this->assertSame(0, $generation->fresh()->credits_reserved);
        $this->assertSame(config('credits.initial_balance'), $credits->account($user)->fresh()->balance);
        $this->assertSame(2, $generation->user->creditAccount->transactions()->count());
    }

    public function test_duplicate_generation_jobs_are_only_dispatched_once(): void
    {
        Cache::flush();
        Queue::fake();
        $user = User::factory()->create();
        $generation = $this->createGeneration($user);

        ProcessGeneration::dispatch($generation->id);
        ProcessGeneration::dispatch($generation->id);

        Queue::assertPushed(ProcessGeneration::class, 1);
    }

    public function test_completed_generation_does_not_execute_again(): void
    {
        $user = User::factory()->create();
        $generation = $this->createGeneration($user);
        $generation->update(['status' => 'completed']);
        $gateway = \Mockery::mock(AiGateway::class);
        $gateway->shouldReceive('generate')->never();

        (new ProcessGeneration($generation->id))->handle($gateway, app(CreditService::class));

        $this->assertSame(0, $generation->jobs()->count());
    }

    private function createGeneration(User $user): Generation
    {
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);

        return $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'queued', 'prompt_hash' => hash('sha256', 'prompt'), 'metadata' => ['aspect_ratio' => '1:1']]);
    }
}
