<?php

namespace Database\Factories;

use App\Models\DonateMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DonateMethod>
 */
class DonateMethodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => 'local',
            'preset' => 'custom',
            'label' => fake()->company(),
            'url' => fake()->url(),
            'symbol' => null,
            'address' => null,
        ];
    }

    public function local(): static
    {
        return $this->state(['category' => 'local', 'symbol' => null, 'address' => null]);
    }

    public function global(): static
    {
        return $this->state(['category' => 'global', 'symbol' => null, 'address' => null]);
    }

    public function crypto(): static
    {
        return $this->state([
            'category' => 'crypto',
            'preset' => 'btc',
            'label' => 'Bitcoin',
            'symbol' => 'BTC',
            'url' => null,
            'address' => fake()->sha256(),
        ]);
    }
}
