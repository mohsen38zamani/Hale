<?php

namespace App\Domains\AI\Contracts;

use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;

interface GenerationProvider
{
    public function key(): string;

    /**
     * Whether this provider can serve the whole request, not just its type.
     * A reference photo is part of that decision: a model that cannot see
     * the product has no business answering a request that has one.
     */
    public function supports(GenerationInput $input): bool;

    public function generate(GenerationInput $input): GenerationResult;
}
