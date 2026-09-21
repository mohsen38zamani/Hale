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

    public function fallback(string $type, ?int $durationSeconds, GenerationProvider $failedProvider): ?GenerationProvider
    {
        $foundFailed = false;
        foreach ($this->providers as $provider) {
            if ($provider === $failedProvider || $provider->key() === $failedProvider->key()) {
                $foundFailed = true;
                continue;
            }

            if ($foundFailed && $provider->supports($type, $durationSeconds)) {
                return $provider;
            }
        }

        // If no provider after the failed one, check any other provider that supports it
        foreach ($this->providers as $provider) {
            if ($provider !== $failedProvider && $provider->key() !== $failedProvider->key() && $provider->supports($type, $durationSeconds)) {
                return $provider;
            }
        }

        return null;
    }
}
