<?php

namespace App\Domains\AI\Router;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use RuntimeException;

class ModelRouter
{
    /** @param iterable<GenerationProvider> $providers */
    public function __construct(private readonly iterable $providers) {}

    /**
     * All providers that can handle the request, in configured priority order.
     * The gateway walks this chain until one of them succeeds.
     *
     * @return list<GenerationProvider>
     */
    public function candidates(GenerationInput $input): array
    {
        $candidates = [];
        foreach ($this->providers as $provider) {
            if ($provider->supports($input)) {
                $candidates[] = $provider;
            }
        }

        if ($candidates === []) {
            throw new RuntimeException('No AI provider supports the requested generation.');
        }

        return $candidates;
    }
}
