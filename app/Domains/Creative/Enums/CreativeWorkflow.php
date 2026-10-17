<?php

namespace App\Domains\Creative\Enums;

/**
 * The six formal studio workflows. Every one-click package names the
 * workflow it belongs to, so the guided studio can group its presets
 * without inventing a taxonomy of its own.
 */
enum CreativeWorkflow: string
{
    case QuickShot = 'quick_shot';
    case Catalog = 'catalog';
    case Lifestyle = 'lifestyle';
    case Seasonal = 'seasonal';
    case CreativeCampaign = 'creative_campaign';
    case Custom = 'custom';
}
