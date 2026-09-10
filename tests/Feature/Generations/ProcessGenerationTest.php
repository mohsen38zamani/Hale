<?php

namespace Tests\Feature\Generations;

use App\Domains\AI\Gateway\AiGateway;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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

        (new ProcessGeneration($id))->handle(app(AiGateway::class));
        $generation = $user->generations()->findOrFail($id);

        $this->assertSame('completed', $generation->status);
        $this->assertSame('local', $generation->provider);
        $this->assertCount(1, $generation->usageLogs);
        Storage::disk('local')->assertExists($generation->outputMedia->path);
    }
}
