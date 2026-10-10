<?php

namespace Tests\Unit\AI;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Router\ModelRouter;
use Illuminate\Support\Facades\Storage;
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

            public function supports(GenerationInput $input): bool
            {
                return $input->type === $this->type
                    && ($this->durations === null || in_array($input->durationSeconds, $this->durations, true));
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

        $keys = array_map(fn (GenerationProvider $provider) => $provider->key(), $router->candidates($this->input('image')));

        $this->assertSame(['image_first', 'image_second'], $keys);
    }

    public function test_candidates_filter_by_supported_duration(): void
    {
        $router = new ModelRouter([
            $this->provider('veo_like', 'video', [5, 8, 10]),
        ]);

        $this->assertCount(1, $router->candidates($this->input('video', 5)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No AI provider supports the requested generation.');

        $router->candidates($this->input('video', 15));
    }

    public function test_candidates_throw_when_no_provider_supports_the_request(): void
    {
        $router = new ModelRouter([
            $this->provider('image_only', 'image'),
        ]);

        $this->expectException(RuntimeException::class);

        $router->candidates($this->input('video', 5));
    }

    public function test_provider_priority_config_reorders_the_fallback_chain(): void
    {
        config(['ai.driver' => 'google', 'ai.provider_priority' => ['local', 'google_imagen']]);
        $this->app->forgetInstance(ModelRouter::class);

        $keys = array_map(
            fn (GenerationProvider $provider) => $provider->key(),
            app(ModelRouter::class)->candidates($this->input('image')),
        );

        $this->assertSame(['local', 'google_imagen'], $keys);
    }

    public function test_registration_order_is_kept_when_no_priority_is_configured(): void
    {
        config(['ai.driver' => 'google', 'ai.provider_priority' => []]);
        $this->app->forgetInstance(ModelRouter::class);

        $keys = array_map(
            fn (GenerationProvider $provider) => $provider->key(),
            app(ModelRouter::class)->candidates($this->input('image')),
        );

        $this->assertSame(['google_imagen', 'local'], $keys);
    }

    private function input(string $type, ?int $durationSeconds = null, ?string $assetDisk = null, ?string $assetPath = null): GenerationInput
    {
        return new GenerationInput(
            type: $type,
            prompt: 'p',
            aspectRatio: '1:1',
            durationSeconds: $durationSeconds,
            assetDisk: $assetDisk,
            assetPath: $assetPath,
        );
    }

    public function test_a_product_photo_routes_a_creative_image_to_the_model_that_can_see_it(): void
    {
        config(['ai.driver' => 'google', 'ai.provider_priority' => []]);
        $this->app->forgetInstance(ModelRouter::class);

        Storage::fake('s3');
        Storage::disk('s3')->put('products/sample.png', 'raw-sample-bytes');

        $router = app(ModelRouter::class);
        $keys = fn (GenerationInput $input): array => array_map(
            fn (GenerationProvider $provider): string => $provider->key(),
            $router->candidates($input),
        );

        $withReference = $keys($this->input('image', null, 's3', 'products/sample.png'));
        $this->assertSame(
            ['google_image_edit', 'google_imagen', 'local'],
            $withReference,
            'The model that can look at the packaging answers first; the rest stay as the fallback chain.'
        );

        $this->assertSame(
            ['google_imagen', 'local'],
            $keys($this->input('image')),
            'With no photo to look at, nothing claims the reference path.'
        );

        // A path that no longer exists is not a reference, so the image must
        // not be routed to a provider that would refuse it.
        $this->assertSame(
            ['google_imagen', 'local'],
            $keys($this->input('image', null, 's3', 'products/missing.png')),
            'A dead path leaves the request where it can still be answered.'
        );
    }
}
