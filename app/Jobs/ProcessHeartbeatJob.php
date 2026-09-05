<?php

namespace App\Jobs;

use App\Models\DeviceSession;
use App\Services\DeviceRegistrar;
use App\Services\GeolocationResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class ProcessHeartbeatJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{device_id: string, session_id: string, app_version: string, os: ?string, os_version: ?string, occurred_at: ?string, ended_at: ?string, ip: ?string}  $data
     */
    public function __construct(private readonly array $data) {}

    public function handle(DeviceRegistrar $registrar, GeolocationResolver $geolocation): void
    {
        $occurredAt = isset($this->data['occurred_at']) ? Carbon::parse($this->data['occurred_at']) : now();

        $device = $registrar->upsert([
            'device_id' => $this->data['device_id'],
            'app_version' => $this->data['app_version'],
            'os' => $this->data['os'] ?? null,
            'os_version' => $this->data['os_version'] ?? null,
        ], $occurredAt);

        $session = DeviceSession::firstOrNew([
            'device_id' => $device->id,
            'client_session_id' => $this->data['session_id'],
        ]);

        if (! $session->exists) {
            $session->started_at = $occurredAt;
            $session->country_code = $geolocation->resolveCountryCode($this->data['ip'] ?? null);
        }

        if (! $session->last_heartbeat_at || $occurredAt->gt($session->last_heartbeat_at)) {
            $session->last_heartbeat_at = $occurredAt;
        }

        $session->app_version = $this->data['app_version'];

        if (! empty($this->data['ended_at'])) {
            $endedAt = Carbon::parse($this->data['ended_at']);
            $session->ended_at = $endedAt;
            $session->duration_seconds = $session->started_at->diffInSeconds($endedAt);
        }

        $session->save();
    }
}
