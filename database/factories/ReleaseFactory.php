<?php

namespace Database\Factories;

use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Release>
 */
class ReleaseFactory extends Factory
{
    protected $model = Release::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => fake()->numerify('1.#.#'),
            'notes' => fake()->sentence(),
            'published_at' => fake()->dateTimeBetween('-90 days', 'now'),
        ];
    }
}
