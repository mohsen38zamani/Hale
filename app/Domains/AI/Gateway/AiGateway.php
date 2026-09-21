<?php

namespace App\Domains\AI\Gateway;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Router\ModelRouter;
use App\Domains\AI\Services\CircuitBreaker;
use Throwable;

class AiGateway
{
    public function __construct(
        private readonly ModelRouter $router,
        private readonly ?CircuitBreaker $circuitBreaker = null,
    ) {}

    /** @return array{provider: string, result: GenerationResult} */
    public function generate(GenerationInput $input): array
    {
        $this->circuitBreaker?->ensureAvailable();

        $provider = $this->router->route($input->type, $input->durationSeconds);
        if ($input->generationId !== null) {
            $this->circuitBreaker?->reserve($input->generationId, $this->estimateCost($input));
        }

        try {
            $result = $provider->generate($input);
            return ['provider' => $provider->key(), 'result' => $result];
        } catch (Throwable $e) {
            $fallback = $this->router->fallback($input->type, $input->durationSeconds, $provider);
            if ($fallback !== null) {
                try {
                    $result = $fallback->generate($input);
                    return ['provider' => $fallback->key(), 'result' => $result];
                } catch (Throwable $fallbackException) {
                    if ($input->generationId !== null) {
                        $this->circuitBreaker?->release($input->generationId);
                    }
                    throw $fallbackException;
                }
            }

            if ($input->generationId !== null) {
                $this->circuitBreaker?->release($input->generationId);
            }

            throw $e;
        }
    }

    private function estimateCost(GenerationInput $input): float
    {
        return $input->type === 'video'
            ? max(1, $input->durationSeconds ?? 5) * 0.05
            : 0.04;
    }
}
