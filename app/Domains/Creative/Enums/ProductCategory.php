<?php

namespace App\Domains\Creative\Enums;

/**
 * The ten trades the studio keeps protection rules, scale locks and
 * one-click packages for. `general` is the fallback a product lands on when
 * nothing else is known about it, so it forbids no vocabulary and locks no
 * scale of its own beyond the truthful generic one.
 *
 * The wording a compiler pastes (scale locks, protected features, banned
 * vocabulary) lives in config/creative_categories.php, keyed by these
 * values, exactly like the other studio catalogues: one source, and the
 * helpers below quote it instead of keeping a second copy.
 */
enum ProductCategory: string
{
    case Jewelry = 'jewelry';
    case Watches = 'watches';
    case Shoes = 'shoes';
    case Perfumes = 'perfumes';
    case Cosmetics = 'cosmetics';
    case Fashion = 'fashion';
    case Bags = 'bags';
    case Electronics = 'electronics';
    case HomeDecor = 'home_decor';
    case General = 'general';

    public function label(): string
    {
        return (string) config("creative_categories.categories.{$this->value}.label", $this->value);
    }

    public function icon(): string
    {
        return (string) config("creative_categories.categories.{$this->value}.icon", '');
    }

    /**
     * Macro framing belongs to the one trade whose product is smaller than a
     * hand: the 100mm lens lock is what stops a ring from growing to bowl
     * size while the model "improves" the composition.
     */
    public function isMacro(): bool
    {
        return $this === self::Jewelry;
    }

    /**
     * The sentence that pins the product to its real-world size, quoted by
     * every compiler so a copied prompt carries the scale with it.
     */
    public function defaultScaleInstruction(): string
    {
        return (string) config("creative_categories.categories.{$this->value}.scale_lock", '');
    }

    public function isFallback(): bool
    {
        return $this === self::General;
    }
}
