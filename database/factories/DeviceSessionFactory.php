<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\DeviceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceSession>
 */
class DeviceSessionFactory extends Factory
{
    protected $model = DeviceSession::class;

    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', 'now');
        $durationSeconds = fake()->numberBetween(60, 6 * 3600);
        $endedAt = (clone $startedAt)->modify("+{$durationSeconds} seconds");

        return [
            'device_id' => Device::factory(),
            'client_session_id' => fake()->uuid(),
            'app_version' => fake()->randomElement(['1.4.0', '1.3.2', '1.3.1']),
            'started_at' => $startedAt,
            'last_heartbeat_at' => $endedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
        ];
    }

    public function active(): static
    {
        return $this->state(function (): array {
            $startedAt = fake()->dateTimeBetween('-2 hours', '-1 minute');

            return [
                'started_at' => $startedAt,
                'last_heartbeat_at' => fake()->dateTimeBetween($startedAt, 'now'),
                'ended_at' => null,
                'duration_seconds' => null,
            ];
        });
    }
}
