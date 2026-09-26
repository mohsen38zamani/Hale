<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Exceptions\AiProviderException;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\AI\Models\AiBudgetReservation;
use App\Domains\AI\Models\AiDailyBudget;
use App\Domains\AI\Router\ModelRouter;
use App\Domains\AI\Services\CircuitBreaker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class AiGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_routes_generation_without_exposing_provider_to_business_logic(): void
    {
        $response = app(AiGateway::class)->generate(new GenerationInput('image', 'product photo', '1:1'));

        $this->assertSame('local', $response['provider']);
        $this->assertSame('local-preview-v1', $response['result']->model);
        $this->assertSame(0.0, $response['result']->costUsd);
    }

    public function test_fake_provider_returns_content_matching_its_declared_format(): void
    {
        $result = app(AiGateway::class)->generate(new GenerationInput('image', 'product photo', '1:1'))['result'];

        $this->assertSame('image/png', $result->mime);
        $this->assertSame('png', $result->extension);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $result->contents);
    }

    public function test_it_walks_the_whole_fallback_chain_until_a_provider_succeeds(): void
    {
        $gateway = new AiGateway(new ModelRouter([
            $this->failingProvider('first', new RuntimeException('network timeout')),
            $this->failingProvider('second', new AiProviderException('محدودیت نرخ درخواست هوش مصنوعی گوگل (429) فرا رسیده است.')),
            $this->workingProvider('third'),
        ]));

        $response = $gateway->generate(new GenerationInput('image', 'prompt', '1:1'));

        $this->assertSame('third', $response['provider']);
        $this->assertSame('third-v1', $response['result']->model);
        $this->assertCount(2, $response['failures']);
        $this->assertSame(['first', 'second'], array_column($response['failures'], 'provider'));
        $this->assertSame('network timeout', $response['failures'][0]['error']);
    }

    public function test_permanent_provider_error_stops_the_chain_and_releases_budget(): void
    {
        config(['ai.daily_budget_usd' => 10.0]);
        $generationId = $this->generationId();

        $never = $this->mock(GenerationProvider::class);
        $never->shouldReceive('supports')->andReturn(true);
        $never->shouldReceive('key')->andReturn('never_used');
        $never->shouldNotReceive('generate');

        $gateway = new AiGateway(
            new ModelRouter([
                $this->failingProvider('permanent', new AiProviderException('خطا در پاسخ هوش مصنوعی گوگل: 400', false)),
                $never,
            ]),
            new CircuitBreaker,
        );

        try {
            $gateway->generate(new GenerationInput('image', 'prompt', '1:1', generationId: $generationId));
            $this->fail('Expected the permanent provider error to be rethrown.');
        } catch (AiProviderException $exception) {
            $this->assertSame('خطا در پاسخ هوش مصنوعی گوگل: 400', $exception->getMessage());
            $this->assertFalse($exception->retryable());
        }

        $this->assertSame(0, AiBudgetReservation::query()->count(), 'Permanent failure must release the reservation.');
    }

    public function test_total_failure_rethrows_the_first_error_and_releases_the_budget(): void
    {
        config(['ai.daily_budget_usd' => 10.0]);
        $generationId = $this->generationId();

        $gateway = new AiGateway(
            new ModelRouter([
                $this->failingProvider('first', new RuntimeException('first error')),
                $this->failingProvider('second', new RuntimeException('second error')),
            ]),
            new CircuitBreaker,
        );

        try {
            $gateway->generate(new GenerationInput('image', 'prompt', '1:1', generationId: $generationId));
            $this->fail('Expected the first provider error to be rethrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('first error', $exception->getMessage());
        }

        $this->assertSame(0, AiBudgetReservation::query()->count());

        $budget = AiDailyBudget::query()->whereDate('budget_date', now())->first();
        $this->assertNotNull($budget);
        $this->assertSame(0.0, (float) $budget->reserved_usd, 'Failed chain must return the reserved budget.');
    }

    public function test_budget_reservation_is_held_when_a_fallback_succeeds(): void
    {
        config(['ai.daily_budget_usd' => 10.0]);
        $generationId = $this->generationId();

        $gateway = new AiGateway(
            new ModelRouter([
                $this->failingProvider('broken', new RuntimeException('timeout')),
                $this->workingProvider('healthy'),
            ]),
            new CircuitBreaker,
        );

        $response = $gateway->generate(new GenerationInput('image', 'prompt', '1:1', generationId: $generationId));

        $this->assertSame('healthy', $response['provider']);
        $this->assertNotNull(
            AiBudgetReservation::query()->where('generation_id', $generationId)->first(),
            'A successful run keeps the reservation so the job can settle actual cost.',
        );
    }

    private function generationId(): int
    {
        $user = User::factory()->create();
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create(['product_id' => $product->id, 'goal' => 'sales', 'style' => 'luxury', 'format' => 'instagram_post', 'brief' => [], 'prompt' => 'prompt']);

        return $user->generations()->create(['creative_project_id' => $project->id, 'type' => 'image', 'status' => 'queued', 'prompt_hash' => hash('sha256', 'prompt'), 'metadata' => ['aspect_ratio' => '1:1']])->id;
    }

    private function workingProvider(string $key): GenerationProvider
    {
        return new class($key) implements GenerationProvider
        {
            public function __construct(private readonly string $keyName) {}

            public function key(): string
            {
                return $this->keyName;
            }

            public function supports(string $type, ?int $durationSeconds = null): bool
            {
                return true;
            }

            public function generate(GenerationInput $input): GenerationResult
            {
                return new GenerationResult('content', 'image/png', 'png', $this->keyName.'-v1', 0.01);
            }
        };
    }

    private function failingProvider(string $key, Throwable $exception): GenerationProvider
    {
        return new class($key, $exception) implements GenerationProvider
        {
            public function __construct(
                private readonly string $keyName,
                private readonly Throwable $exception,
            ) {}

            public function key(): string
            {
                return $this->keyName;
            }

            public function supports(string $type, ?int $durationSeconds = null): bool
            {
                return true;
            }

            public function generate(GenerationInput $input): GenerationResult
            {
                throw $this->exception;
            }
        };
    }
}
