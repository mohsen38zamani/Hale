<?php

declare(strict_types=1);

namespace App\Domains\Generations\Contracts;

use App\Domains\Media\Models\MediaAsset;

/**
 * The post-render pass of foreground-invariant inpainting (spec item 12,
 * phase 3.2): the scene came back from the first model call, and now the
 * customer's product is landed again by the edit model with the reference
 * photo beside it - 100% of the form, the label and the finish,rimmed with
 * light from the scene, while the rest of the frame is left as approved.
 */
interface EdgeProtectPassInterface
{
    /**
     * @param  string  $sceneBytes  The finished render, as it left the provider.
     * @param  string  $sceneMime  The mime the output contract accepted already;
     *                             the pass re-encodes its answer to the same mime so the contract
     *                             never meets a surprise format.
     * @param  MediaAsset  $productAsset  The customer's own product photo.
     * @param  string|null  $category  The trade, whose protection wording rides along.
     * @return array{contents:string,mime:string,model:string,costUsd:float,inputTokens:int,outputTokens:int}|null
     *                                                                                                             Null on any failure: the caller then simply keeps the render
     *                                                                                                             that already exists.
     */
    public function apply(string $sceneBytes, string $sceneMime, MediaAsset $productAsset, ?string $category): ?array;
}
