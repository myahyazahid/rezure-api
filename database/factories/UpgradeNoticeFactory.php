<?php

namespace Database\Factories;

use App\Models\UpgradeNotice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UpgradeNotice>
 */
class UpgradeNoticeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enabled' => true,
            'major' => 4,
            'message' => fake()->sentence(),
            'url' => fake()->url(),
        ];
    }
}
