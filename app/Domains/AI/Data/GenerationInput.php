<?php

namespace App\Domains\AI\Data;

readonly class GenerationInput
{
    /**
     * How the product image takes part in a video, when there is one.
     *
     * reference - the thing belongs in the scene, for as long as the clip
     *            runs. This is the ASSET reference and the default.
     * first    - the clip opens on exactly that picture: instances[0].image.
     * first_last - the clip opens and closes on it: image plus
     *            instances[0].lastFrame, which the service documents as
     *            image-to-video only, so the first frame comes with it.
     *
     * The three are alternatives, not additions: the service refuses
     * referenceImages in the same request as image or lastFrame.
     */
    public function __construct(
        public string $type,
        public string $prompt,
        public string $aspectRatio,
        public ?int $durationSeconds = null,
        public ?string $assetDisk = null,
        public ?string $assetPath = null,
        public ?int $generationId = null,
        public string $imageMode = 'reference',
    ) {}
}
