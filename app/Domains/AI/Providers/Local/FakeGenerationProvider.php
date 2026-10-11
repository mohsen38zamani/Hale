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

    public function supports(GenerationInput $input): bool
    {
        return $input->type === 'image'
            || $input->type === 'image_edit'
            || ($input->type === 'video' && in_array($input->durationSeconds, [5, 8, 10], true));
    }

    public function generate(GenerationInput $input): GenerationResult
    {
        if ($input->type === 'video') {
            // Structurally an MP4 - just its ftyp box, nothing to play. The
            // pipeline checks that box for video, so a picture here would
            // be refused as an invalid output and every local run of the
            // video mode would fail on its own placeholder.
            return new GenerationResult(
                "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom",
                'video/mp4',
                'mp4',
                'local-preview-v1',
                0,
                metadata: ['fake' => true],
            );
        }

        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return new GenerationResult($contents, 'image/png', 'png', 'local-preview-v1', 0, metadata: ['fake' => true]);
    }
}
