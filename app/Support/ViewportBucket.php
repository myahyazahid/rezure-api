<?php

namespace App\Support;

/**
 * Viewport width → the coarse layout bucket (mobile / tablet / desktop /
 * wide) the click heatmap and Technology page group by. Bounds live in
 * config/web-analytics.php.
 */
class ViewportBucket
{
    public static function for(?int $width): ?string
    {
        if ($width === null || $width <= 0) {
            return null;
        }

        foreach (config('web-analytics.viewport_buckets') as $bucket => $maxWidth) {
            if ($width <= $maxWidth) {
                return $bucket;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(config('web-analytics.viewport_buckets'));
    }
}
