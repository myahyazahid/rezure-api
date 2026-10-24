<?php

namespace Database\Factories;

use App\Models\WebSession;
use App\Models\WebVisitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<WebSession>
 */
class WebSessionFactory extends Factory
{
    protected $model = WebSession::class;

    public function definition(): array
    {
        $startedAt = Carbon::instance(fake()->dateTimeBetween('-30 days', '-1 hour'));
        $duration = fake()->numberBetween(0, 600);

        return [
            'session_uuid' => fake()->uuid(),
            'web_visitor_id' => WebVisitor::factory(),
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addSeconds($duration),
            'duration_seconds' => $duration,
            'engaged_seconds' => intdiv($duration, 2),
            'pageviews_count' => 1,
            'events_count' => 0,
            'is_engaged' => false,
            'is_bounce' => true,
            'has_conversion' => false,
            'is_new_visitor' => true,
            'visit_number' => 1,
            'entry_path' => '/',
            'exit_path' => '/',
            'channel' => 'Direct',
            'source' => 'Direct',
            'ip_address' => fake()->ipv4(),
            'country_code' => 'ID',
            'region' => 'Jakarta',
            'city' => 'Jakarta',
            'browser' => 'Chrome',
            'browser_version' => '120.0',
            'browser_engine' => 'Blink',
            'os' => 'Windows',
            'os_version' => '10',
            'device_type' => 'desktop',
            'viewport_width' => 1440,
            'viewport_bucket' => 'wide',
            'screen_width' => 1920,
            'screen_height' => 1080,
            'language' => 'id-ID',
        ];
    }

    public function engaged(): static
    {
        return $this->state(fn (): array => ['is_engaged' => true, 'is_bounce' => false]);
    }

    public function converted(): static
    {
        return $this->state(fn (): array => ['has_conversion' => true, 'is_engaged' => true, 'is_bounce' => false]);
    }

    public function at(Carbon $startedAt, int $durationSeconds = 60): static
    {
        return $this->state(fn (): array => [
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addSeconds($durationSeconds),
            'duration_seconds' => $durationSeconds,
        ]);
    }
}
