<?php

namespace App\Services;

use League\ISO3166\Exception\OutOfBoundsException;
use League\ISO3166\ISO3166;

/**
 * Converts a stored 2-letter country_code into the full name the dashboard
 * displays — the raw code is what's indexed and grouped on, the name is
 * only ever a presentation-layer lookup.
 */
class CountryNameResolver
{
    public function __construct(private readonly ISO3166 $iso3166) {}

    public function name(?string $countryCode): string
    {
        if (! $countryCode) {
            return 'Unknown';
        }

        try {
            return $this->iso3166->alpha2($countryCode)['name'];
        } catch (OutOfBoundsException) {
            return 'Unknown';
        }
    }
}
