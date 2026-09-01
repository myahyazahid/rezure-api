<?php

namespace App\Support;

/**
 * Small display-formatting helpers shared across dashboard Blade views —
 * kept out of the views themselves so the rounding rule lives in one place.
 */
class Formatting
{
    /**
     * Compact form for badges and stat tiles: 1615 -> "1.6k", 42 -> "42".
     */
    public static function compact(int $value): string
    {
        if ($value < 1000) {
            return (string) $value;
        }

        return round($value / 1000, 1).'k';
    }
}
