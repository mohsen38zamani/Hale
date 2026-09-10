<?php

namespace App\Domains\AI\Services;

class PromptModerator
{
    public function passes(string $prompt): bool
    {
        foreach (config('ai.moderation.blocked_terms', []) as $term) {
            if (mb_stripos($prompt, $term) !== false) {
                return false;
            }
        }

        return true;
    }
}
