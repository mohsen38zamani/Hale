<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Gateway\AiGateway;
use App\Domains\AI\Models\AiDailyBudget;
use App\Domains\AI\Router\ModelRouter;
use App\Domains\AI\Services\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_circuit_breaker_allows_when_under_daily_budget(): void
    {
        config(['ai.daily_budget_usd' => 10.0]);

        $circuitBreaker = new CircuitBreaker;
        $this->assertTrue($circuitBreaker->isAvailable());
    }

    public function test_circuit_breaker_blocks_when_daily_budget_is_exceeded(): void
    {
        config(['ai.daily_budget_usd' => 5.0]);

        AiDailyBudget::query()->create(['budget_date' => now()->toDateString(), 'spent_usd' => 6.00]);

        $circuitBreaker = new CircuitBreaker;
        $this->assertFalse($circuitBreaker->isAvailable());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('سقف بودجه روزانه مصرف هوش مصنوعی تکمیل شده است.');

        $circuitBreaker->ensureAvailable();
    }

    public function test_gateway_triggers_fallback_when_primary_provider_fails(): void
    {
        $primary = new class implements GenerationProvider
        {
            public function key(): string
            {
                return 'failing_primary';
            }

            public function supports(string $type, ?int $durationSeconds = null): bool
            {
                return true;
            }

            public function generate(GenerationInput $input): GenerationResult
            {
                throw new RuntimeException('Primary provider network timeout');
            }
        };

        $fallback = new class implements GenerationProvider
        {
            public function key(): string
            {
                return 'working_fallback';
            }

            public function supports(string $type, ?int $durationSeconds = null): bool
            {
                return true;
            }

            public function generate(GenerationInput $input): GenerationResult
            {
                return new GenerationResult('fake-fallback-content', 'image/png', 'png', 'fallback-v1', 0.01);
            }
        };

        $router = new ModelRouter([$primary, $fallback]);
        $gateway = new AiGateway($router);

        $response = $gateway->generate(new GenerationInput('image', 'prompt', '1:1'));

        $this->assertSame('working_fallback', $response['provider']);
        $this->assertSame('fallback-v1', $response['result']->model);
    }
}
