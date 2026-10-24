<?php

namespace App\Services\WebAnalytics;

use Illuminate\Support\Facades\DB;

/**
 * SQL expressions that bucket a UTC timestamp column by the dashboard's
 * display timezone (Asia/Jakarta) — "today" and "14:00" on the website
 * analytics pages mean the maintainer's day and hour, not UTC's.
 *
 * Timestamps are shifted by the display timezone's current UTC offset,
 * which is exact for zones without daylight saving (WIB). Picked per
 * connection, like GenerateTrafficSummaries::hourExpression(), because
 * tests run on sqlite and production on MySQL/MariaDB.
 */
class LocalTime
{
    public static function offsetSeconds(): int
    {
        return now(config('app.display_timezone'))->utcOffset() * 60;
    }

    /**
     * The column moved into display-timezone wall-clock time.
     */
    public static function shifted(string $column): string
    {
        $offset = self::offsetSeconds();

        return match (self::driver()) {
            'sqlite' => sprintf("datetime(%s, '%+d seconds')", $column, $offset),
            'pgsql' => sprintf("(%s + INTERVAL '%d seconds')", $column, $offset),
            default => sprintf('DATE_ADD(%s, INTERVAL %d SECOND)', $column, $offset),
        };
    }

    /**
     * A string key per bucket: `Y-m-d H:00` (hour), `Y-m-d` (day, and the
     * Monday that starts a week) or `Y-m-01` (month).
     */
    public static function bucket(string $column, string $granularity): string
    {
        $local = self::shifted($column);

        return match (self::driver()) {
            'sqlite' => match ($granularity) {
                'hour' => "strftime('%Y-%m-%d %H:00', {$local})",
                'week' => "date({$local}, '-6 days', 'weekday 1')",
                'month' => "strftime('%Y-%m-01', {$local})",
                default => "date({$local})",
            },
            'pgsql' => match ($granularity) {
                'hour' => "to_char({$local}, 'YYYY-MM-DD HH24:00')",
                'week' => "to_char(date_trunc('week', {$local}), 'YYYY-MM-DD')",
                'month' => "to_char(date_trunc('month', {$local}), 'YYYY-MM-DD')",
                default => "to_char({$local}, 'YYYY-MM-DD')",
            },
            default => match ($granularity) {
                'hour' => "DATE_FORMAT({$local}, '%Y-%m-%d %H:00')",
                'week' => "DATE_FORMAT(DATE_SUB({$local}, INTERVAL WEEKDAY({$local}) DAY), '%Y-%m-%d')",
                'month' => "DATE_FORMAT({$local}, '%Y-%m-01')",
                default => "DATE_FORMAT({$local}, '%Y-%m-%d')",
            },
        };
    }

    /**
     * 0–23 in the display timezone.
     */
    public static function hourOfDay(string $column): string
    {
        $local = self::shifted($column);

        return match (self::driver()) {
            'sqlite' => "CAST(strftime('%H', {$local}) AS INTEGER)",
            'pgsql' => "EXTRACT(HOUR FROM {$local})",
            default => "HOUR({$local})",
        };
    }

    /**
     * ISO weekday, 1 (Monday) – 7 (Sunday), in the display timezone.
     */
    public static function dayOfWeekIso(string $column): string
    {
        $local = self::shifted($column);

        return match (self::driver()) {
            'sqlite' => "(((CAST(strftime('%w', {$local}) AS INTEGER) + 6) % 7) + 1)",
            'pgsql' => "EXTRACT(ISODOW FROM {$local})",
            default => "(WEEKDAY({$local}) + 1)",
        };
    }

    /**
     * Portable string concatenation for computed dimensions (`1920x1080`).
     */
    public static function concat(string ...$parts): string
    {
        return match (self::driver()) {
            'sqlite', 'pgsql' => '('.implode(' || ', $parts).')',
            default => 'CONCAT('.implode(', ', $parts).')',
        };
    }

    private static function driver(): string
    {
        return DB::connection()->getDriverName();
    }
}
