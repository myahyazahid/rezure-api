<?php

namespace App\Jobs;

use App\Models\Ticket;
use App\Services\DeviceRegistrar;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessTicketSubmissionJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{device_id: string, client_ticket_id: string, category: string, title: string, description: string, app_version: ?string, os_version: ?string, attachments: list<array{file_path: string, file_name: string, file_size: int, mime_type: string}>}  $data
     */
    public function __construct(private readonly array $data) {}

    public function handle(DeviceRegistrar $registrar): void
    {
        $device = $registrar->upsert([
            'device_id' => $this->data['device_id'],
            'app_version' => $this->data['app_version'] ?? null,
        ], now());

        $ticket = Ticket::where('device_id', $device->id)
            ->where('client_ticket_id', $this->data['client_ticket_id'])
            ->first();

        if ($ticket) {
            // Duplicate submission (client retry). The existing ticket
            // already owns its attachments from the first successful run —
            // the files this invocation stored are orphaned copies (random
            // names, so they never collided with the originals). Delete
            // just those, never touch the existing ticket's attachments.
            foreach ($this->data['attachments'] as $attachment) {
                Storage::disk('local')->delete($attachment['file_path']);
            }

            return;
        }

        DB::transaction(function () use ($device): void {
            $ticket = Ticket::create([
                'device_id' => $device->id,
                'client_ticket_id' => $this->data['client_ticket_id'],
                'category' => $this->data['category'],
                'title' => $this->data['title'],
                'description' => $this->data['description'],
                'app_version' => $this->data['app_version'] ?? null,
                'os_version' => $this->data['os_version'] ?? null,
            ]);

            foreach ($this->data['attachments'] as $attachment) {
                $ticket->attachments()->create($attachment);
            }
        });
    }
}
