<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Router\ModelRouter;
use RuntimeException;
use Tests\TestCase;

class ModelRouterTest extends TestCase
{
    /**
     * @param  list<int>|null  $durations
     */
    private function provider(string $key, string $type, ?array $durations = null): GenerationProvider
    {
        return new class($key, $type, $durations) implements GenerationProvider
        {
            public function __construct(
                private readonly string $keyName,
                private readonly string $type,
                private readonly ?array $durations,
            ) {}

            public function key(): string
            {
                return $this->keyName;
            }

            public function supports(string $type, ?int $durationSeconds = null): bool
            {
                return $type === $this->type
                    && ($this->durations === null || in_array($durationSeconds, $this->durations, true));
            }

            public function generate(GenerationInput $input): GenerationResult
            {
                return new GenerationResult('content', 'image/png', 'png', 'model-v1', 0.0);
            }
        };
    }

    public function test_candidates_return_supporting_providers_in_priority_order(): void
    {
        $router = new ModelRouter([
            $this->provider('video_only', 'video'),
            $this->provider('image_first', 'image'),
            $this->provider('image_second', 'image'),
        ]);

        $keys = array_map(fn (GenerationProvider $provider) => $provider->key(), $router->candidates('image'));

        $this->assertSame(['image_first', 'image_second'], $keys);
    }

    public function test_candidates_filter_by_supported_duration(): void
    {
        $router = new ModelRouter([
            $this->provider('veo_like', 'video', [5, 8, 10]),
        ]);

        $this->assertCount(1, $router->candidates('video', 5));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No AI provider supports the requested generation.');

        $router->candidates('video', 15);
    }

    public function test_candidates_throw_when_no_provider_supports_the_request(): void
    {
        $router = new ModelRouter([
            $this->provider('image_only', 'image'),
        ]);

        $this->expectException(RuntimeException::class);

        $router->candidates('video', 5);
    }
}
