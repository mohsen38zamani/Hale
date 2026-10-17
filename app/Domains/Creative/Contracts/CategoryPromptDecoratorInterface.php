<?php

namespace App\Domains\Creative\Contracts;

/**
 * What a trade adds to a compiled prompt, whatever the grammar around it:
 * the clause that pins the product to its real-world size, the clause that
 * protects the features no model may redraw, and the signature nouns of the
 * other trades that must never drift into this one's scene.
 *
 * Every compiler asks for these; none of them may invent its own wording -
 * the catalogue in config/creative_categories.php decides what a trade says.
 */
interface CategoryPromptDecoratorInterface
{
    /**
     * `Physical scale: ...` for the trade; empty when there is no trade.
     */
    public function scale(?string $category): string;

    /**
     * `Keep ... unchanged.` for the trade; empty when there is no trade.
     */
    public function guardrail(?string $category): string;

    /**
     * The signature nouns of the other trades; empty when there is no trade.
     *
     * @return list<string>
     */
    public function bannedWords(?string $category): array;
}
