<?php

use Torann\GeoIP\Services\IPApi;
use Torann\GeoIP\Services\IPData;
use Torann\GeoIP\Services\IPFinder;
use Torann\GeoIP\Services\IPGeoLocation;
use Torann\GeoIP\Services\MaxMindDatabase;
use Torann\GeoIP\Services\MaxMindWebService;

return [

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log settings for when a location is not found
    | for the IP provided.
    |
    */

    'log_failures' => true,

    /*
    |--------------------------------------------------------------------------
    | Include Currency in Results
    |--------------------------------------------------------------------------
    |
    | When enabled the system will do it's best in deciding the user's currency
    | by matching their ISO code to a preset list of currencies.
    |
    */

    'include_currency' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Service
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default storage driver that should be used
    | by the framework using the services listed below.
    |
    */

    'service' => env('GEOIP_SERVICE', 'ip_api'),

    /*
    |--------------------------------------------------------------------------
    | Storage Specific Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many storage drivers as you wish.
    |
    */

    'services' => [

        // Free, no API key/account required (docs/v3 Fase 3.1: chosen over
        // MaxMind's local database to avoid needing a MaxMind license key).
        // Rate-limited to 45 req/min on ip-api.com's free tier — App\Services\
        // GeolocationResolver caches per-IP for 6h to stay well under that.
        'ip_api' => [
            'class' => IPApi::class,
            'key' => env('IPAPI_KEY'),
            'secure' => true,
            'lang' => 'en',
            'continent_path' => storage_path('app/continents.json'),
        ],

        'maxmind_database' => [
            'class' => MaxMindDatabase::class,
            'database_path' => storage_path('app/geoip.mmdb'),
            'update_url' => sprintf('https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-City&license_key=%s&suffix=tar.gz', env('MAXMIND_LICENSE_KEY')),
            'locales' => ['en'],
        ],

        'maxmind_api' => [
            'class' => MaxMindWebService::class,
            'user_id' => env('MAXMIND_USER_ID'),
            'license_key' => env('MAXMIND_LICENSE_KEY'),
            'locales' => ['en'],
        ],

        'ipgeolocation' => [
            'class' => IPGeoLocation::class,
            'secure' => true,
            'key' => env('IPGEOLOCATION_KEY'),
            'continent_path' => storage_path('app/continents.json'),
            'lang' => 'en',
        ],

        'ipdata' => [
            'class' => IPData::class,
            'key' => env('IPDATA_API_KEY'),
            'secure' => true,
        ],

        'ipfinder' => [
            'class' => IPFinder::class,
            'key' => env('IPFINDER_API_KEY'),
            'secure' => true,
            'locales' => ['en'],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Cache Driver
    |--------------------------------------------------------------------------
    |
    | Here you may specify the type of caching that should be used
    | by the package.
    |
    | Options:
    |
    |  all  - All location are cached
    |  some - Cache only the requesting user
    |  none - Disable cached
    |
    */

    // Disabled: the package's own cache uses cache tags, which the app's
    // default 'database' cache store doesn't support. App\Services\
    // GeolocationResolver already caches per-IP lookups on its own.
    'cache' => 'none',

    /*
    |--------------------------------------------------------------------------
    | Cache Tags
    |--------------------------------------------------------------------------
    |
    | Cache tags are not supported when using the file or database cache
    | drivers in Laravel. This is done so that only locations can be cleared.
    |
    | Must stay empty: torann/geoip's GeoIP constructor builds its internal
    | Cache wrapper unconditionally on boot (it ignores the 'cache' => 'none'
    | setting above) and calls Cache::tags() whenever this array is non-empty
    | — which throws "This cache store does not support tagging" against the
    | app's database cache store on every single lookup, before an IP is ever
    | resolved. GeolocationResolver already caches per-IP on its own.
    |
    */

    'cache_tags' => [],

    /*
    |--------------------------------------------------------------------------
    | Cache Expiration
    |--------------------------------------------------------------------------
    |
    | Define how long cached location are valid.
    |
    */

    'cache_expires' => 30,

    /*
    |--------------------------------------------------------------------------
    | Default Location
    |--------------------------------------------------------------------------
    |
    | Return when a location is not found.
    |
    */

    'default_location' => [
        'ip' => '127.0.0.0',
        'iso_code' => 'US',
        'country' => 'United States',
        'city' => 'New Haven',
        'state' => 'CT',
        'state_name' => 'Connecticut',
        'postal_code' => '06510',
        'lat' => 41.31,
        'lon' => -72.92,
        'timezone' => 'America/New_York',
        'continent' => 'NA',
        'default' => true,
        'currency' => 'USD',
    ],

];
