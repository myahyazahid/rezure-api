<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketAttachment>
 */
class TicketAttachmentFactory extends Factory
{
    protected $model = TicketAttachment::class;

    public function definition(): array
    {
        $fileName = fake()->word().'.png';

        return [
            'ticket_id' => Ticket::factory(),
            'file_path' => 'ticket-attachments/'.fake()->uuid().'/'.fake()->uuid().'.png',
            'file_name' => $fileName,
            'file_size' => fake()->numberBetween(1024, 2_000_000),
            'mime_type' => 'image/png',
        ];
    }
}
