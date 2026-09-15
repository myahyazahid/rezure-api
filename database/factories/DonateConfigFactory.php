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
            'local' => [
                ['label' => 'Trakteer', 'url' => 'https://trakteer.id/example'],
            ],
            'global' => [
                ['label' => 'GitHub Sponsors', 'url' => 'https://github.com/sponsors/example'],
            ],
            'crypto' => [
                ['symbol' => 'BTC', 'label' => 'Bitcoin', 'address' => fake()->sha256()],
            ],
        ];
    }
}
