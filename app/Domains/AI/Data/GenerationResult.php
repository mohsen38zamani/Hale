<?php

namespace App\Domains\AI\Data;

readonly class GenerationResult
{
    public function __construct(
        public string $contents,
        public string $mime,
        public string $extension,
        public string $model,
        public float $costUsd,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public array $metadata = [],
    ) {}
}
