<?php

namespace App\Support;

use App\Models\WebGoal;
use App\Services\CountryNameResolver;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use App\Services\WebAnalytics\WebDimensions;
use League\ISO3166\Exception\DomainException;
use League\ISO3166\Exception\OutOfBoundsException;
use League\ISO3166\ISO3166;

/**
 * Display formatting for the website analytics pages: durations, rates,
 * and how a raw dimension value (a country code, an empty referrer) reads
 * to a person. Values stay raw in queries and URLs; only views call this.
 */
class WebDisplay
{
    /**
     * 0 → "0s", 75 → "1m 15s", 3725 → "1h 2m".
     */
    public static function duration(int|float|null $seconds): string
    {
        $seconds = (int) round((float) $seconds);

        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            return intdiv($seconds, 60).'m '.($seconds % 60).'s';
        }

        return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
    }

    public static function percent(int|float|null $value, int $decimals = 1): string
    {
        return number_format((float) $value, $decimals).'%';
    }

    public static function number(int|float|null $value): string
    {
        $value = (float) $value;

        return floor($value) == $value ? number_format($value) : number_format($value, 2);
    }

    /**
     * How a breakdown value is labelled for a given dimension.
     */
    public static function value(string $dimension, ?string $value): string
    {
        if ($value === null || $value === '' || $value === WebAnalyticsQuery::NONE) {
            return match ($dimension) {
                'referrer', 'source' => 'Direct / none',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' => 'Not tagged',
                default => 'Unknown',
            };
        }

        // A filter value can be typed into the URL — only real codes are looked up.
        if ($dimension === 'country' && preg_match('/^[A-Za-z]{2}$/', $value)) {
            return self::flag($value).' '.app(CountryNameResolver::class)->name($value);
        }

        return $value;
    }

    /**
     * How an active filter reads in its chip — a goal filter holds the
     * goal's id, everything else a dimension value.
     */
    public static function filterValue(string $key, string $value): string
    {
        if ($key === WebDimensions::GOAL) {
            return WebGoal::find((int) $value)->name ?? 'Goal #'.$value;
        }

        if ($key === WebDimensions::PAGE) {
            return $value;
        }

        return self::value($key, $value);
    }

    /**
     * Regional-indicator emoji for a 2-letter country code.
     */
    public static function flag(?string $countryCode): string
    {
        if ($countryCode === null || ! preg_match('/^[A-Za-z]{2}$/', $countryCode)) {
            return '🏳';
        }

        return implode('', array_map(
            fn (string $letter): string => mb_chr(0x1F1E6 + ord($letter) - ord('A')),
            str_split(strtoupper($countryCode)),
        ));
    }

    /**
     * ISO 3166-1 numeric code — what world-atlas's country shapes are keyed
     * by on the map.
     */
    public static function numericCountryCode(?string $countryCode): ?string
    {
        // "(none)" and other non-codes make league/iso3166 throw.
        if ($countryCode === null || ! preg_match('/^[A-Za-z]{2}$/', $countryCode)) {
            return null;
        }

        try {
            return app(ISO3166::class)->alpha2($countryCode)['numeric'];
        } catch (OutOfBoundsException|DomainException) {
            return null;
        }
    }
}
