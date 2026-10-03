<?php

namespace App\Domains\Creative\Services;

use Carbon\CarbonInterface;

class SeasonThemeService
{
    /**
     * Resolve the active seasonal studio theme for a given day.
     *
     * @return array<string, mixed>|null
     */
    public function active(?CarbonInterface $today = null): ?array
    {
        $themes = config('seasons.themes', []);
        $override = (string) config('seasons.active', 'auto');

        if ($override !== '' && $override !== 'auto') {
            if (in_array($override, ['off', 'none'], true)) {
                return null;
            }

            // Pinned key: only that theme can be active, whatever the date.
            foreach ($themes as $theme) {
                if (($theme['key'] ?? null) === $override) {
                    return $theme;
                }
            }

            return null;
        }

        $date = now()->format('m-d');
        foreach ($themes as $theme) {
            if ($this->inRange($date, (string) $theme['start'], (string) $theme['end'])) {
                return $theme;
            }
        }

        return null;
    }

    /** Month-day windows may wrap around New Year (start > end). */
    private function inRange(string $date, string $start, string $end): bool
    {
        if ($start <= $end) {
            return $date >= $start && $date <= $end;
        }

        return $date >= $start || $date <= $end;
    }
}
