<?php

namespace App\Services\WebAnalytics;

/**
 * Every session-level dimension the website dashboard can break down or
 * filter by, and the SQL expression behind it. One registry so the
 * breakdown tables, the filter chips, the drill-down links and the daily
 * dimension rollup can never disagree about what "country" means.
 */
class WebDimensions
{
    /**
     * Filters that aren't a web_sessions column — handled by
     * WebAnalyticsQuery directly.
     */
    public const PAGE = 'page';

    public const GOAL = 'goal';

    /**
     * @var array<string, array{label: string, column: string}>
     */
    private const SESSION_DIMENSIONS = [
        'channel' => ['label' => 'Channel', 'column' => 'channel'],
        'source' => ['label' => 'Source', 'column' => 'source'],
        'referrer' => ['label' => 'Referrer', 'column' => 'referrer_host'],
        'utm_source' => ['label' => 'UTM source', 'column' => 'utm_source'],
        'utm_medium' => ['label' => 'UTM medium', 'column' => 'utm_medium'],
        'utm_campaign' => ['label' => 'UTM campaign', 'column' => 'utm_campaign'],
        'utm_term' => ['label' => 'UTM term', 'column' => 'utm_term'],
        'utm_content' => ['label' => 'UTM content', 'column' => 'utm_content'],
        'entry_page' => ['label' => 'Entry page', 'column' => 'entry_path'],
        'exit_page' => ['label' => 'Exit page', 'column' => 'exit_path'],
        'country' => ['label' => 'Country', 'column' => 'country_code'],
        'region' => ['label' => 'Region', 'column' => 'region'],
        'city' => ['label' => 'City', 'column' => 'city'],
        'language' => ['label' => 'Language', 'column' => 'language'],
        'timezone' => ['label' => 'Browser timezone', 'column' => 'timezone'],
        'browser' => ['label' => 'Browser', 'column' => 'browser'],
        'browser_engine' => ['label' => 'Engine', 'column' => 'browser_engine'],
        'os' => ['label' => 'OS', 'column' => 'os'],
        'device_type' => ['label' => 'Device type', 'column' => 'device_type'],
        'device_brand' => ['label' => 'Brand', 'column' => 'device_brand'],
        'device_model' => ['label' => 'Model', 'column' => 'device_model'],
        'viewport' => ['label' => 'Viewport', 'column' => 'viewport_bucket'],
        'color_scheme' => ['label' => 'OS color scheme', 'column' => 'color_scheme'],
        'site_theme' => ['label' => 'Site theme', 'column' => 'site_theme'],
        'connection' => ['label' => 'Connection', 'column' => 'connection'],
    ];

    /**
     * Dimensions derived from more than one column.
     *
     * @var array<string, string>
     */
    private const COMPUTED_LABELS = [
        'screen' => 'Screen resolution',
        'browser_version' => 'Browser version',
        'os_version' => 'OS version',
        'visitor_type' => 'New vs returning',
    ];

    /**
     * @return list<string>
     */
    public static function sessionKeys(): array
    {
        return [...array_keys(self::SESSION_DIMENSIONS), ...array_keys(self::COMPUTED_LABELS)];
    }

    /**
     * Every key a dashboard URL may filter by.
     *
     * @return list<string>
     */
    public static function filterKeys(): array
    {
        return [...self::sessionKeys(), self::PAGE, self::GOAL];
    }

    public static function isSessionDimension(string $key): bool
    {
        return isset(self::SESSION_DIMENSIONS[$key]) || isset(self::COMPUTED_LABELS[$key]);
    }

    public static function label(string $key): string
    {
        return self::SESSION_DIMENSIONS[$key]['label']
            ?? self::COMPUTED_LABELS[$key]
            ?? match ($key) {
                self::PAGE => 'Page',
                self::GOAL => 'Goal',
                default => ucfirst(str_replace('_', ' ', $key)),
            };
    }

    /**
     * The SQL expression for a session dimension, qualified with `$table`
     * (which may be an alias).
     */
    public static function expression(string $key, string $table = 'web_sessions'): string
    {
        if (isset(self::SESSION_DIMENSIONS[$key])) {
            return $table.'.'.self::SESSION_DIMENSIONS[$key]['column'];
        }

        return match ($key) {
            'screen' => LocalTime::concat("{$table}.screen_width", "'x'", "{$table}.screen_height"),
            'browser_version' => LocalTime::concat("{$table}.browser", "' '", "{$table}.browser_version"),
            'os_version' => LocalTime::concat("{$table}.os", "' '", "{$table}.os_version"),
            'visitor_type' => "CASE WHEN {$table}.is_new_visitor = 1 THEN 'New' ELSE 'Returning' END",
            default => throw new \InvalidArgumentException("Unknown website dimension [{$key}]."),
        };
    }
}
