<?php

namespace App\Domains\AI\Providers\Local;

use App\Domains\AI\Contracts\GenerationProvider;
use App\Domains\AI\Data\GenerationInput;
use App\Domains\AI\Data\GenerationResult;

class FakeGenerationProvider implements GenerationProvider
{
    public function key(): string
    {
        return 'local';
    }

    public function supports(string $type, ?int $durationSeconds = null): bool
    {
        return $type === 'image' || ($type === 'video' && in_array($durationSeconds, [5, 8, 10], true));
    }

    public function generate(GenerationInput $input): GenerationResult
    {
        $contents = sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1080"><rect width="100%%" height="100%%" fill="#111827"/><text x="50%%" y="50%%" fill="white" text-anchor="middle" font-size="42">Hale Preview</text></svg>');

        return new GenerationResult($contents, 'image/svg+xml', 'svg', 'local-preview-v1', 0, metadata: ['fake' => true]);
    }
}
