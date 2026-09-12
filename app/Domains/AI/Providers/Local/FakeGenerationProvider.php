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
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return new GenerationResult($contents, 'image/png', 'png', 'local-preview-v1', 0, metadata: ['fake' => true]);
    }
}
