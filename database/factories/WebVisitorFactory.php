<?php

namespace Database\Factories;

use App\Models\WebVisitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebVisitor>
 */
class WebVisitorFactory extends Factory
{
    protected $model = WebVisitor::class;

    public function definition(): array
    {
        $firstSeen = fake()->dateTimeBetween('-30 days', '-1 hour');

        return [
            'visitor_uuid' => fake()->uuid(),
            'first_seen_at' => $firstSeen,
            'last_seen_at' => $firstSeen,
            'sessions_count' => 0,
            'pageviews_count' => 0,
            'first_landing_path' => '/',
            'first_channel' => 'Direct',
            'first_source' => 'Direct',
            'last_ip_address' => fake()->ipv4(),
            'last_country_code' => 'ID',
            'last_city' => 'Jakarta',
            'last_browser' => 'Chrome',
            'last_os' => 'Windows',
            'last_device_type' => 'desktop',
        ];
    }
}
