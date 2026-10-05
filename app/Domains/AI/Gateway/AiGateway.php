<?php

namespace App\Domains\AI\Gateway;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Exceptions\AiProviderException;
use App\Domains\AI\Router\ModelRouter;
use App\Domains\AI\Services\CircuitBreaker;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AiGateway
{
    public function __construct(
        private readonly ModelRouter $router,
        private readonly ?CircuitBreaker $circuitBreaker = null,
    ) {}

    /**
     * Try every capable provider in priority order until one succeeds.
     *
     * @return array{provider: string, result: GenerationResult, failures: list<array{provider: string, error: string}>}
     */
    public function generate(GenerationInput $input): array
    {
        $this->circuitBreaker?->ensureAvailable();

        $candidates = $this->router->candidates($input->type, $input->durationSeconds);

        if ($input->generationId !== null) {
            $this->circuitBreaker?->reserve($input->generationId, $this->estimateCost($input, $candidates[0]));
        }

        /** @var list<array{provider: string, error: string}> $failures */
        $failures = [];
        $firstException = null;

        foreach ($candidates as $index => $provider) {
            if ($index > 0 && ($this->circuitBreaker?->isAvailable() ?? true) === false) {
                // Daily budget was exhausted mid-chain (e.g. by concurrent
                // generations): stop instead of starting another provider.
                break;
            }

            try {
                $result = $provider->generate($input);

                if ($index > 0) {
                    Log::warning('ai.provider.fallback', [
                        'generation_id' => $input->generationId,
                        'used_provider' => $provider->key(),
                        'failures' => $failures,
                    ]);
                }

                return ['provider' => $provider->key(), 'result' => $result, 'failures' => $failures];
            } catch (AiProviderException $exception) {
                $firstException ??= $exception;
                $failures[] = ['provider' => $provider->key(), 'error' => $exception->getMessage()];

                if (! $exception->retryable()) {
                    // Permanent request errors would fail identically on the
                    // next provider, so stop instead of burning time/budget.
                    break;
                }
            } catch (Throwable $exception) {
                // Unknown errors are treated as transient so a provider-specific
                // failure still gets a fallback chance.
                $firstException ??= $exception;
                $failures[] = ['provider' => $provider->key(), 'error' => $exception->getMessage()];
            }
        }

        if ($input->generationId !== null) {
            $this->circuitBreaker?->release($input->generationId);
        }

        throw $firstException ?? new RuntimeException('هیچ provider هوش مصنوعی نتوانست درخواست را انجام دهد.');
    }

    private function estimateCost(GenerationInput $input, GenerationProvider $provider): float
    {
        $pricing = (array) config('ai.pricing.default', []);
        foreach ((array) config('ai.pricing.'.$provider->key(), []) as $key => $value) {
            if ($value !== null) {
                $pricing[$key] = $value;
            }
        }

        if ($input->type === 'video') {
            return max(1, $input->durationSeconds ?? 5) * (float) ($pricing['video_per_second'] ?? 0.05);
        }

        if ($input->type === 'image_edit') {
            return (float) ($pricing['edit'] ?? $pricing['image'] ?? 0.04);
        }

        return (float) ($pricing['image'] ?? 0.04);
    }
}
