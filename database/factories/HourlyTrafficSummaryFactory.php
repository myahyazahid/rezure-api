<?php

namespace Database\Factories;

use App\Models\HourlyTrafficSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HourlyTrafficSummary>
 */
class HourlyTrafficSummaryFactory extends Factory
{
    protected $model = HourlyTrafficSummary::class;

    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'hour' => fake()->numberBetween(0, 23),
            'event_count' => fake()->numberBetween(0, 500),
        ];
    }
}
