<?php

namespace Database\Factories;

use App\Models\CountryTrafficSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CountryTrafficSummary>
 */
class CountryTrafficSummaryFactory extends Factory
{
    protected $model = CountryTrafficSummary::class;

    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'country_code' => fake()->randomElement(['ID', 'US', 'SG', 'MY', 'IN']),
            'device_count' => fake()->numberBetween(1, 100),
        ];
    }
}
