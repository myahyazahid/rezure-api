<?php

namespace Database\Factories;

use App\Models\Changelog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Changelog>
 */
class ChangelogFactory extends Factory
{
    protected $model = Changelog::class;

    public function definition(): array
    {
        return [
            'version' => fake()->numerify('1.#.#'),
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(3, true),
            'released_at' => fake()->dateTimeBetween('-90 days', 'now'),
        ];
    }
}
