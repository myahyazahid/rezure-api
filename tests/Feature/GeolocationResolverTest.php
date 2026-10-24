<?php

namespace Tests\Feature;

use App\Services\GeolocationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Torann\GeoIP\Facades\GeoIP;
use Torann\GeoIP\Location;

class GeolocationResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_a_country_code_from_the_geoip_service(): void
    {
        GeoIP::shouldReceive('getLocation')
            ->once()
            ->with('203.0.113.10')
            ->andReturn(new Location(['iso_code' => 'id', 'default' => false]));

        $countryCode = app(GeolocationResolver::class)->resolveCountryCode('203.0.113.10');

        $this->assertSame('ID', $countryCode);
    }

    public function test_resolves_a_city_level_location_for_website_analytics(): void
    {
        GeoIP::shouldReceive('getLocation')
            ->once()
            ->with('203.0.113.10')
            ->andReturn(new Location([
                'iso_code' => 'id', 'state_name' => 'West Java', 'city' => 'Bandung', 'postal_code' => '40111',
                'lat' => -6.91, 'lon' => 107.61, 'timezone' => 'Asia/Jakarta', 'default' => false,
            ]));

        $resolver = app(GeolocationResolver::class);
        $location = $resolver->resolveLocation('203.0.113.10');

        $this->assertSame([
            'country_code' => 'ID',
            'region' => 'West Java',
            'city' => 'Bandung',
            'postal_code' => '40111',
            'latitude' => -6.91,
            'longitude' => 107.61,
            'timezone' => 'Asia/Jakarta',
        ], $location);

        // Cached per IP, like the country lookup.
        $this->assertSame($location, $resolver->resolveLocation('203.0.113.10'));
    }

    public function test_resolve_location_skips_private_ips(): void
    {
        GeoIP::shouldReceive('getLocation')->never();

        $this->assertNull(app(GeolocationResolver::class)->resolveLocation('10.0.0.5'));
    }

    public function test_returns_null_without_calling_geoip_for_a_private_ip(): void
    {
        GeoIP::shouldReceive('getLocation')->never();

        $countryCode = app(GeolocationResolver::class)->resolveCountryCode('192.168.1.10');

        $this->assertNull($countryCode);
    }

    public function test_returns_null_for_a_missing_ip(): void
    {
        GeoIP::shouldReceive('getLocation')->never();

        $countryCode = app(GeolocationResolver::class)->resolveCountryCode(null);

        $this->assertNull($countryCode);
    }

    public function test_returns_null_when_the_geoip_service_only_has_a_default_fallback_location(): void
    {
        GeoIP::shouldReceive('getLocation')
            ->once()
            ->andReturn(new Location(['iso_code' => 'US', 'default' => true]));

        $countryCode = app(GeolocationResolver::class)->resolveCountryCode('203.0.113.10');

        $this->assertNull($countryCode);
    }

    public function test_a_repeated_lookup_for_the_same_ip_is_served_from_cache_instead_of_calling_geoip_again(): void
    {
        GeoIP::shouldReceive('getLocation')
            ->once()
            ->andReturn(new Location(['iso_code' => 'ID', 'default' => false]));

        $resolver = app(GeolocationResolver::class);

        $this->assertSame('ID', $resolver->resolveCountryCode('203.0.113.10'));
        $this->assertSame('ID', $resolver->resolveCountryCode('203.0.113.10'));
    }

    public function test_a_failed_lookup_is_swallowed_and_logged_instead_of_failing_the_job(): void
    {
        Log::shouldReceive('warning')->once();

        GeoIP::shouldReceive('getLocation')->once()->andThrow(new \Exception('service unavailable'));

        $countryCode = app(GeolocationResolver::class)->resolveCountryCode('203.0.113.10');

        $this->assertNull($countryCode);
    }
}
