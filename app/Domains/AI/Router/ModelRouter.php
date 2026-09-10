<?php

namespace App\Domains\AI\Router;

use App\Domains\AI\Contracts\GenerationProvider;
use RuntimeException;

class ModelRouter
{
    /** @param iterable<GenerationProvider> $providers */
    public function __construct(private readonly iterable $providers) {}

    public function route(string $type, ?int $durationSeconds = null): GenerationProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($type, $durationSeconds)) {
                return $provider;
            }
        }

        throw new RuntimeException('No AI provider supports the requested generation.');
    }
}
