<?php

namespace App\Domains\AI\Data;

readonly class GenerationInput
{
    public function __construct(
        public string $type,
        public string $prompt,
        public string $aspectRatio,
        public ?int $durationSeconds = null,
        public ?string $assetDisk = null,
        public ?string $assetPath = null,
        public ?int $generationId = null,
    ) {}
}
