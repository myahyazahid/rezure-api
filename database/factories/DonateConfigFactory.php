<?php

namespace Database\Factories;

use App\Models\DonateConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DonateConfig>
 */
class DonateConfigFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'message' => fake()->sentence(),
        ];
    }
}
