<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\DeviceRegistrar;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class ProcessEventJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{device_id: string, event_id: string, event_type: string, event_name: ?string, app_version: string, payload: ?array, occurred_at: ?string}  $data
     */
    public function __construct(private readonly array $data) {}

    public function handle(DeviceRegistrar $registrar): void
    {
        $occurredAt = isset($this->data['occurred_at']) ? Carbon::parse($this->data['occurred_at']) : now();

        $device = $registrar->upsert([
            'device_id' => $this->data['device_id'],
            'app_version' => $this->data['app_version'],
        ], $occurredAt);

        // firstOrCreate on the (device_id, client_event_id) unique key is
        // the idempotency guard: a client retrying an offline-queued event
        // lands here twice with the same event_id and the second call is a
        // no-op instead of a duplicate row.
        Event::firstOrCreate(
            [
                'device_id' => $device->id,
                'client_event_id' => $this->data['event_id'],
            ],
            [
                'event_type' => $this->data['event_type'],
                'event_name' => $this->data['event_name'] ?? null,
                'app_version' => $this->data['app_version'],
                'payload' => $this->data['payload'] ?? null,
                'occurred_at' => $occurredAt,
            ]
        );
    }
}
