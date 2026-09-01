<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'client_ticket_id' => fake()->uuid(),
            'category' => fake()->randomElement(['bug', 'feature_request', 'general']),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => 'open',
            'app_version' => fake()->randomElement(['1.4.0', '1.3.2', '1.3.1']),
            'os_version' => fake()->randomElement(['23H2', '22H2', '21H2']),
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
