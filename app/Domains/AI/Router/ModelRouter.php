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
        $afterFailed = false;
        $beforeFallback = null;

        foreach ($this->providers as $provider) {
            if ($provider === $failedProvider || $provider->key() === $failedProvider->key()) {
                $afterFailed = true;

                continue;
            }

            if ($provider->supports($type, $durationSeconds)) {
                if ($afterFailed) {
                    return $provider;
                }
                $beforeFallback ??= $provider;
            }
        }

        return $beforeFallback;
    }
}
