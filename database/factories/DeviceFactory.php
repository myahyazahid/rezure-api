<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        $firstSeen = fake()->dateTimeBetween('-90 days', '-1 day');

        return [
            'device_id' => fake()->uuid(),
            'app_version' => fake()->randomElement(['1.4.0', '1.4.0', '1.3.2', '1.3.1', '1.2.0']),
            'os' => fake()->randomElement(['Windows 11', 'Windows 11', 'Windows 10', 'Windows 10', 'Windows 11']),
            'os_version' => fake()->randomElement(['23H2', '22H2', '21H2']),
            'telemetry_opted_out' => false,
            'first_seen_at' => $firstSeen,
            'last_seen_at' => fake()->dateTimeBetween($firstSeen, 'now'),
        ];
    }

    public function optedOut(): static
    {
        return $this->state(fn (): array => ['telemetry_opted_out' => true]);
    }

    public function dormant(): static
    {
        return $this->state(fn (): array => [
            'last_seen_at' => fake()->dateTimeBetween('-90 days', '-45 days'),
        ]);
    }
}
