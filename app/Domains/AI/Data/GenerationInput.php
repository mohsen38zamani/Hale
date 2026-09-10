<?php

namespace App\Domains\AI\Data;

readonly class GenerationInput
{
    public function __construct(
        public string $type,
        public string $prompt,
        public string $aspectRatio,
        public ?int $durationSeconds = null,
    ) {}
}
