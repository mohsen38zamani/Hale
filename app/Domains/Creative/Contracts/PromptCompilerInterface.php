<?php

namespace App\Domains\Creative\Contracts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * One model family, one way of assembling a prompt.
 *
 * Every compiler receives the same brief the studio built and returns the
 * wording that model expects; only the structure differs (XML blocks for
 * Claude, parameters for Midjourney, front-loaded motion for Veo, ...). The
 * brief itself never changes, so switching target cannot silently change the
 * scene the user configured.
 */
interface PromptCompilerInterface
{
    /**
     * The `target_ai` key this compiler answers to.
     */
    public function key(): string;

    /**
     * @param  array<string, mixed>  $brief
     */
    public function compile(array $brief, CreativeFormat $format): string;
}
