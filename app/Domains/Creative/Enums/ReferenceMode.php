<?php

namespace App\Domains\Creative\Enums;

/**
 * What a reference image is asked to convey: the style worth borrowing, or
 * the scene the product should be rebuilt inside. The two read the same
 * upload very differently, so the mode travels with the request instead of
 * being guessed.
 */
enum ReferenceMode: string
{
    case StyleInspo = 'style_inspo';
    case SceneInspo = 'scene_inspo';
}
