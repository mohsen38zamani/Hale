<?php

namespace App\Domains\AI\Contracts;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;

interface GenerationProvider
{
    public function key(): string;

    public function supports(string $type, ?int $durationSeconds = null): bool;

    public function generate(GenerationInput $input): GenerationResult;
}
