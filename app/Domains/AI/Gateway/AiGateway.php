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

        try {
            return ['provider' => $provider->key(), 'result' => $provider->generate($input)];
        } catch (Throwable $e) {
            $fallback = $this->router->fallback($input->type, $input->durationSeconds, $provider);
            if ($fallback !== null) {
                return ['provider' => $fallback->key(), 'result' => $fallback->generate($input)];
            }

            throw $e;
        }
    }
}
