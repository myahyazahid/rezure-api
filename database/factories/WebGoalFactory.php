<?php

namespace Database\Factories;

use App\Models\WebGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebGoal>
 */
class WebGoalFactory extends Factory
{
    protected $model = WebGoal::class;

    public function definition(): array
    {
        return [
            'name' => 'Download',
            'type' => WebGoal::TYPE_EVENT,
            'match' => 'download',
            'label_match' => null,
            'is_active' => true,
        ];
    }

    public function pageview(string $pattern): static
    {
        return $this->state(fn (): array => ['name' => 'Visited '.$pattern, 'type' => WebGoal::TYPE_PAGEVIEW, 'match' => $pattern]);
    }
}
