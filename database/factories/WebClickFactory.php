<?php

namespace Database\Factories;

use App\Models\WebClick;
use App\Models\WebPageview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebClick>
 */
class WebClickFactory extends Factory
{
    protected $model = WebClick::class;

    public function definition(): array
    {
        return [
            'click_uuid' => fake()->uuid(),
            'web_pageview_id' => WebPageview::factory(),
            'web_session_id' => fn (array $attributes): int => WebPageview::find($attributes['web_pageview_id'])->web_session_id,
            'path' => fn (array $attributes): string => WebPageview::find($attributes['web_pageview_id'])->path,
            'x_pct' => fake()->randomFloat(2, 0, 100),
            'y_px' => fake()->numberBetween(0, 3000),
            'page_height' => 3200,
            'viewport_width' => 1440,
            'viewport_bucket' => 'wide',
            'selector' => 'a.VPButton',
            'tag' => 'a',
            'text' => 'Download',
            'is_rage' => false,
            'occurred_at' => fn (array $attributes) => WebPageview::find($attributes['web_pageview_id'])->occurred_at,
        ];
    }
}
