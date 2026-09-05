<?php

namespace Tests\Unit;

use App\Services\CountryNameResolver;
use Tests\TestCase;

class CountryNameResolverTest extends TestCase
{
    public function test_resolves_a_full_country_name_from_its_code(): void
    {
        $this->assertSame('Indonesia', app(CountryNameResolver::class)->name('ID'));
    }

    public function test_resolves_case_insensitively(): void
    {
        $this->assertSame('Indonesia', app(CountryNameResolver::class)->name('id'));
    }

    public function test_returns_unknown_for_a_null_code(): void
    {
        $this->assertSame('Unknown', app(CountryNameResolver::class)->name(null));
    }

    public function test_returns_unknown_for_an_unrecognized_code(): void
    {
        $this->assertSame('Unknown', app(CountryNameResolver::class)->name('ZZ'));
    }
}
