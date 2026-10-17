<?php

namespace App\Domains\Creative\Enums;

/**
 * Which fidelity pipeline a scene is built through:
 *
 * - The catalog white composite places the untouched cutout on pure
 *   #FFFFFF with a grounding shadow - our own pixels, no model between the
 *   product and the page.
 * - Foreground-invariant inpainting hands the product to the edit model
 *   with a protection mask, so only the scene around it is allowed to
 *   change.
 */
enum FidelityMode: string
{
    case CatalogWhiteComposite = 'catalog_white_composite';
    case ForegroundInvariantInpainting = 'foreground_invariant_inpainting';

    /**
     * Whether our own compositor renders the frame instead of a model.
     */
    public function isComposite(): bool
    {
        return $this === self::CatalogWhiteComposite;
    }
}
