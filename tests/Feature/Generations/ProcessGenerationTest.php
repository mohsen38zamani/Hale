<?php

namespace Tests\Feature\Generations;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Exceptions\AiProviderException;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\AI\Providers\Local\FakeGenerationProvider;
use App\Domains\AI\Router\ModelRouter;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ProcessGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Redis::connection('default')->flushdb();
    }

    public function test_generation_is_dispatched_and_pipeline_tracks_output_and_cost(): void
    {
        Queue::fake();
        Storage::fake('s3');
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
        $this->assertTrue(Storage::disk('s3')->exists($generation->outputMedia->path));
    }

    public function test_a_video_generation_completes_and_lands_as_an_mp4(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_reel',
            'brief' => [],
            'prompt' => 'prompt',
            'video_duration_seconds' => 5,
        ]);
        $generation = $user->generations()->create([
            'creative_project_id' => $project->id,
            'type' => 'video',
            'status' => 'queued',
            'prompt_hash' => hash('sha256', 'prompt'),
            'metadata' => ['aspect_ratio' => '9:16'],
        ]);

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), app(CreditService::class));
        $generation->refresh();

        // The video mode is the one output that has no real provider behind
        // it without a key, so it lives or dies on the placeholder being a
        // video too - a picture here would be refused by the output contract
        // and the mode would fail on its own fallback.
        $this->assertSame('completed', $generation->status);
        $this->assertSame('video/mp4', $generation->outputMedia->mime);
        $this->assertSame('ftyp', substr((string) Storage::disk('s3')->get($generation->outputMedia->path), 4, 4));
    }

    public function test_the_recorded_frame_choice_reaches_the_provider(): void
    {
        $provider = $this->recordingProvider();
        $generation = $this->queuedVideo(['aspect_ratio' => '9:16', 'image_mode' => 'first_last']);

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), app(CreditService::class));

        // A choice nobody ever carried to the provider would be a setting
        // on a form and nothing more; this is the only claim worth making.
        $this->assertSame('first_last', $provider->seen?->imageMode);
    }

    public function test_a_video_queued_before_the_choice_existed_is_still_a_reference(): void
    {
        $provider = $this->recordingProvider();
        $generation = $this->queuedVideo(['aspect_ratio' => '9:16']);

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), app(CreditService::class));

        // Generations carry metadata from the day they were queued, so the
        // key is simply absent on the older ones - and absent has to mean
        // what it has always meant.
        $this->assertSame('reference', $provider->seen?->imageMode);
    }

    /**
     * The local placeholder, wearing a note: it answers exactly as the
     * fake always has, and keeps the input it was handed.
     */
    private function recordingProvider(): object
    {
        $provider = new class extends FakeGenerationProvider
        {
            public ?GenerationInput $seen = null;

            public function generate(GenerationInput $input): GenerationResult
            {
                $this->seen = $input;

                return parent::generate($input);
            }
        };
        $this->app->instance(AiGateway::class, new AiGateway(new ModelRouter([$provider])));

        return $provider;
    }

    /** @param  array<string, mixed>  $metadata */
    private function queuedVideo(array $metadata): Generation
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_reel',
            'brief' => [],
            'prompt' => 'prompt',
            'video_duration_seconds' => 5,
        ]);

        return $user->generations()->create([
            'creative_project_id' => $project->id,
            'type' => 'video',
            'status' => 'queued',
            'prompt_hash' => hash('sha256', 'prompt'),
            'metadata' => $metadata,
        ]);
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
        Storage::fake('s3');
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
        Redis::connection('default')->flushdb();
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

    public function test_failed_primary_provider_falls_back_and_records_failures_in_metadata(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        $generation = $this->createGeneration($user);
        $credits = app(CreditService::class);
        $credits->reserve($user, $generation, 10);

        $rateLimitedPrimary = new class implements GenerationProvider
        {
            public function key(): string
            {
                return 'google_imagen';
            }

            public function supports(GenerationInput $input): bool
            {
                return $input->type === 'image';
            }

            public function generate(GenerationInput $input): GenerationResult
            {
                throw new AiProviderException('محدودیت نرخ درخواست هوش مصنوعی گوگل (429) فرا رسیده است.');
            }
        };
        $this->app->instance(ModelRouter::class, new ModelRouter([
            $rateLimitedPrimary,
            app(FakeGenerationProvider::class),
        ]));

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), $credits);

        $generation->refresh();
        $this->assertSame('completed', $generation->status);
        $this->assertSame('local', $generation->provider);
        $this->assertSame('google_imagen', $generation->metadata['provider_failures'][0]['provider']);
        $this->assertSame('محدودیت نرخ درخواست هوش مصنوعی گوگل (429) فرا رسیده است.', $generation->metadata['provider_failures'][0]['error']);
        $this->assertSame(10, $generation->credits_charged);
        $this->assertSame(0, $generation->user->creditAccount->fresh()->reserved);
    }

    public function test_an_image_with_the_products_own_photo_is_generated_by_the_model_that_can_see_it(): void
    {
        Storage::fake('s3');
        config([
            'ai.driver' => 'google',
            'ai.provider_priority' => [],
            'ai.providers.google.api_key' => 'test-key',
        ]);
        $this->app->forgetInstance(ModelRouter::class);

        $user = User::factory()->create();
        $generation = $this->createGeneration($user);
        $asset = $user->mediaAssets()->create(['path' => 'products/sample.png', 'disk' => 's3', 'mime' => 'image/png', 'size' => 17]);
        Storage::disk('s3')->put('products/sample.png', 'product-photo-bytes');
        $generation->creativeProject->product->assets()->attach($asset->id, ['is_primary' => true]);

        // A 1x1 PNG, the smallest thing that still satisfies the output contract.
        $outputPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($outputPng)],
                    ]]],
                ]],
            ], 200),
        ]);

        $credits = app(CreditService::class);
        $credits->reserve($user, $generation, 10);

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), $credits);

        $generation->refresh();
        $this->assertSame('completed', $generation->status, (string) $generation->error_message);
        $this->assertSame('google_image_edit', $generation->provider, 'The product photo must reach a model that can see it.');

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return str_contains($request->url(), ':generateContent')
                && ($payload['contents'][0]['parts'][0]['inline_data']['data'] ?? null) === base64_encode('product-photo-bytes')
                && isset($payload['contents'][0]['parts'][1]['text']);
        });
    }

    public function test_the_protect_pass_lands_the_product_in_a_completed_inpainting_generation(): void
    {
        Storage::fake('s3');
        config(['ai.providers.google.api_key' => 'test-key']);
        $user = User::factory()->create(['plan_key' => 'pro']);
        $generation = $this->queuedGenerationWithFidelity($user, 'foreground_invariant_inpainting');
        $answer = $this->passPngBytes();

        Http::fake(['*generativelanguage.googleapis.com*' => Http::response($this->passAnswerPayload($answer), 200)]);

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), app(CreditService::class));

        $generation->refresh();
        $this->assertSame('completed', $generation->status, (string) $generation->error_message);
        $this->assertSame('applied', $generation->metadata['edge_protect']);

        // The pass earns its own row on the ledger, clearly addressed, while
        // the user's charge stays the render's own price.
        $usageLogs = $generation->usageLogs()->orderBy('id')->get();
        $this->assertCount(2, $usageLogs);
        $this->assertSame('google_image_edit', $usageLogs[1]->provider);
        $this->assertSame('edge_protect', $usageLogs[1]->metadata['pass']);
        $this->assertSame((float) config('ai.edge_protect.cost_usd'), (float) $usageLogs[1]->cost_usd);

        // On the pro plan the watermark is skipped and the quality tier never
        // upscales a small frame, so the stored render is exactly the pass.
        $this->assertSame($answer, (string) Storage::disk('s3')->get($generation->outputMedia->path));
    }

    public function test_a_failing_protect_pass_still_delivers_the_render(): void
    {
        Storage::fake('s3');
        config(['ai.providers.google.api_key' => 'test-key']);
        $generation = $this->queuedGenerationWithFidelity(User::factory()->create(), 'foreground_invariant_inpainting');

        Http::fake(['*generativelanguage.googleapis.com*' => Http::response('boom', 500)]);

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), app(CreditService::class));

        $generation->refresh();
        $this->assertSame('completed', $generation->status, 'The pass only decorates a finished render; its refusal never breaks one.');
        $this->assertSame('failed', $generation->metadata['edge_protect']);
        $this->assertCount(1, $generation->usageLogs, 'A refused pass has no cost to record.');

        // The render that left the local fake is what survived the refusal.
        $stored = (string) Storage::disk('s3')->get($generation->outputMedia->path);
        $this->assertSame(1, imagesx(imagecreatefromstring($stored)));
    }

    public function test_a_scene_without_the_inpainting_mode_never_incurs_the_pass(): void
    {
        foreach (['catalog_white_composite', null] as $fidelityMode) {
            Storage::fake('s3');
            Http::fake(['*generativelanguage.googleapis.com*' => Http::response($this->passAnswerPayload($this->passPngBytes()), 200)]);

            $generation = $this->queuedGenerationWithFidelity(User::factory()->create(), $fidelityMode);

            (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), app(CreditService::class));

            $generation->refresh();
            $this->assertSame('completed', $generation->status, (string) $generation->error_message);
            $this->assertArrayNotHasKey('edge_protect', $generation->metadata);
            $this->assertCount(1, $generation->usageLogs);
            // Not every outbound call in a pipeline is ours; web push rides
            // the notification too. What must not happen is a model call.
            $this->assertTrue(
                Http::recorded(fn ($request): bool => str_contains($request->url(), 'generativelanguage'))->isEmpty(),
                'A scene without the inpainting mode must never reach the pass model.'
            );
        }
    }

    public function test_the_protect_pass_uses_brief_category_fallback_when_project_category_is_null(): void
    {
        Storage::fake('s3');
        config(['ai.providers.google.api_key' => 'test-key']);
        $user = User::factory()->create(['plan_key' => 'pro']);
        $product = $user->products()->create(['name' => 'انگشتر نقره']);
        $assetBytes = $this->passPngBytes();
        $asset = $user->mediaAssets()->create(['path' => 'products/reference.png', 'disk' => 's3', 'mime' => 'image/png', 'size' => strlen($assetBytes)]);
        Storage::disk('s3')->put('products/reference.png', $assetBytes);
        $product->assets()->attach($asset->id, ['is_primary' => true]);

        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'professional',
            'format' => 'instagram_post',
            'category' => null,
            'fidelity_mode' => 'foreground_invariant_inpainting',
            'brief' => ['category' => 'jewelry'],
            'prompt' => 'prompt',
        ]);

        $generation = $user->generations()->create([
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'queued',
            'prompt_hash' => hash('sha256', 'prompt'),
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);
        app(CreditService::class)->reserve($user, $generation, 10);

        Http::fake(['*generativelanguage.googleapis.com*' => Http::response($this->passAnswerPayload($this->passPngBytes()), 200)]);

        (new ProcessGeneration($generation->id))->handle(app(AiGateway::class), app(CreditService::class));

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'generativelanguage')) {
                return false;
            }

            $prompt = (string) json_decode($request->body(), true)['contents'][0]['parts'][2]['text'];
            $this->assertStringContainsString('macro photography, 100mm lens', $prompt, 'The brief category must reach the protect pass prompt.');

            return true;
        });
    }

    private function queuedGenerationWithFidelity(User $user, ?string $fidelityMode): Generation
    {
        $product = $user->products()->create(['name' => 'انگشتر نقره']);
        $assetBytes = $this->passPngBytes();
        $asset = $user->mediaAssets()->create(['path' => 'products/reference.png', 'disk' => 's3', 'mime' => 'image/png', 'size' => strlen($assetBytes)]);
        Storage::disk('s3')->put('products/reference.png', $assetBytes);
        $product->assets()->attach($asset->id, ['is_primary' => true]);

        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'professional',
            'format' => 'instagram_post',
            'category' => 'jewelry',
            'fidelity_mode' => $fidelityMode,
            'brief' => [],
            'prompt' => 'prompt',
        ]);

        $generation = $user->generations()->create([
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'queued',
            'prompt_hash' => hash('sha256', 'prompt'),
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);
        app(CreditService::class)->reserve($user, $generation, 10);

        return $generation;
    }

    private function passPngBytes(): string
    {
        $image = imagecreatetruecolor(16, 16);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /**
     * @return array<string, mixed>
     */
    private function passAnswerPayload(string $contents): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [[
                    'inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($contents)],
                ]]],
            ]],
            'usageMetadata' => ['promptTokenCount' => 9, 'candidatesTokenCount' => 4],
        ];
    }

    private function createGeneration(User $user): Generation
    {
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);

        return $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'queued', 'prompt_hash' => hash('sha256', 'prompt'), 'metadata' => ['aspect_ratio' => '1:1']]);
    }
}
