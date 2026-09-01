<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * event_type => plausible event_name values, mirroring what the Rezure
     * desktop client actually reports (see rezureapp/src-tauri/src/commands).
     */
    private const TYPES = [
        'service.start' => ['nginx', 'apache', 'mysql', 'mariadb', 'php-fpm'],
        'service.stop' => ['nginx', 'apache', 'mysql', 'mariadb', 'php-fpm'],
        'project.create' => ['laravel', 'wordpress', 'vue-starter', 'static'],
        'runtime.switch' => ['php 8.3.2', 'php 8.2.15', 'php 8.1.27'],
        'error.report' => ['PortBindException', 'ServiceStartTimeout', 'ConfigParseError'],
    ];

    public function definition(): array
    {
        $type = fake()->randomElement(array_keys(self::TYPES));
        $occurredAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'device_id' => Device::factory(),
            'client_event_id' => fake()->uuid(),
            'event_type' => $type,
            'event_name' => fake()->randomElement(self::TYPES[$type]),
            'app_version' => fake()->randomElement(['1.4.0', '1.3.2', '1.3.1']),
            'payload' => null,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ];
    }

    public function type(string $type): static
    {
        return $this->state(fn (): array => [
            'event_type' => $type,
            'event_name' => fake()->randomElement(self::TYPES[$type] ?? [null]),
        ]);
    }
}
