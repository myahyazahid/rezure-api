<?php

namespace Database\Factories;

use App\Models\WebPageview;
use App\Models\WebSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebPageview>
 */
class WebPageviewFactory extends Factory
{
    protected $model = WebPageview::class;

    public function definition(): array
    {
        $path = fake()->randomElement(['/', '/download', '/guide/installation', '/guide/first-run', '/blog']);

        return [
            'pageview_uuid' => fake()->uuid(),
            'web_session_id' => WebSession::factory(),
            'web_visitor_id' => fn (array $attributes): int => WebSession::find($attributes['web_session_id'])->web_visitor_id,
            'url' => 'https://rezure.redscale.my.id'.$path,
            'path' => $path,
            'title' => 'Rezure',
            'sequence' => 1,
            'occurred_at' => fn (array $attributes) => WebSession::find($attributes['web_session_id'])->started_at,
            'engaged_ms' => fake()->numberBetween(0, 120000),
            'max_scroll_pct' => fake()->numberBetween(0, 100),
            'is_entry' => true,
            'is_exit' => true,
        ];
    }
}
