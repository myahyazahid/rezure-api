<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;
use Torann\GeoIP\Facades\GeoIP;

/**
 * Resolves a request IP to a 2-letter country code, on the fly, at ingest
 * time. The IP itself is never persisted — only the resolved code is handed
 * back to the caller — and results are cached briefly per IP so a device
 * hammering heartbeats/events doesn't trigger a lookup against the external
 * geolocation service for every single one (torann/geoip's default driver
 * here is Ip-Api, a free third-party API — see docs/v3 Fase 3.1 for why a
 * local MaxMind database was considered and deferred).
 */
class GeolocationResolver
{
    private const CACHE_TTL_SECONDS = 21600;

    public function resolveCountryCode(?string $ip): ?string
    {
        if (! $ip || $this->isPrivateOrReserved($ip)) {
            return null;
        }

        return Cache::remember(
            'geoip:country:'.hash('sha256', $ip),
            self::CACHE_TTL_SECONDS,
            fn (): ?string => $this->lookup($ip),
        );
    }

    /**
     * Full location for website analytics (v4), which — unlike desktop
     * telemetry — keeps city-level data. Cached per IP like the country
     * lookup, under its own key so the two never mix shapes.
     *
     * @return array{country_code: string, region: ?string, city: ?string, postal_code: ?string, latitude: ?float, longitude: ?float, timezone: ?string}|null
     */
    public function resolveLocation(?string $ip): ?array
    {
        if (! $ip || $this->isPrivateOrReserved($ip)) {
            return null;
        }

        return Cache::remember(
            'geoip:location:'.hash('sha256', $ip),
            self::CACHE_TTL_SECONDS,
            fn (): ?array => $this->lookupLocation($ip),
        );
    }

    /**
     * @return array{country_code: string, region: ?string, city: ?string, postal_code: ?string, latitude: ?float, longitude: ?float, timezone: ?string}|null
     */
    private function lookupLocation(string $ip): ?array
    {
        try {
            $location = GeoIP::getLocation($ip);

            if ($location->default || empty($location->iso_code)) {
                return null;
            }

            return [
                'country_code' => strtoupper($location->iso_code),
                'region' => $this->nonEmpty($location->state_name),
                'city' => $this->nonEmpty($location->city),
                'postal_code' => $this->nonEmpty($location->postal_code),
                'latitude' => is_numeric($location->lat) ? (float) $location->lat : null,
                'longitude' => is_numeric($location->lon) ? (float) $location->lon : null,
                'timezone' => $this->nonEmpty($location->timezone),
            ];
        } catch (Throwable $e) {
            Log::warning('Geolocation lookup failed', ['exception' => $e->getMessage()]);

            return null;
        }
    }

    private function nonEmpty(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function lookup(string $ip): ?string
    {
        try {
            $location = GeoIP::getLocation($ip);

            if ($location->default || empty($location->iso_code)) {
                return null;
            }

            return strtoupper($location->iso_code);
        } catch (Throwable $e) {
            // A flaky third-party geolocation service must never fail the
            // job processing the event/heartbeat itself — just skip it.
            Log::warning('Geolocation lookup failed', ['exception' => $e->getMessage()]);

            return null;
        }
    }

    private function isPrivateOrReserved(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
