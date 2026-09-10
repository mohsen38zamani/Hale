<?php

namespace App\Domains\AI\Gateway;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;
use App\Domains\AI\Router\ModelRouter;

class AiGateway
{
    public function __construct(private readonly ModelRouter $router) {}

    /** @return array{provider: string, result: GenerationResult} */
    public function generate(GenerationInput $input): array
    {
        $provider = $this->router->route($input->type, $input->durationSeconds);

        return ['provider' => $provider->key(), 'result' => $provider->generate($input)];
    }
}
