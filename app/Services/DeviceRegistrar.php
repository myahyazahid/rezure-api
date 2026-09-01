<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Carbon;

/**
 * Upserts the device row that every heartbeat and event ultimately belongs
 * to. Shared by both ingestion jobs so "how a device gets registered" has
 * one definition.
 */
class DeviceRegistrar
{
    /**
     * @param  array{device_id: string, app_version?: ?string, os?: ?string, os_version?: ?string}  $attributes
     */
    public function upsert(array $attributes, Carbon $seenAt): Device
    {
        $device = Device::firstOrNew(['device_id' => $attributes['device_id']]);

        if (! $device->exists) {
            $device->first_seen_at = $seenAt;
        }

        // Clients retry after being offline, so an older, out-of-order
        // report must not rewind what we already know about this device.
        if (! $device->last_seen_at || $seenAt->gt($device->last_seen_at)) {
            $device->last_seen_at = $seenAt;
            $device->app_version = $attributes['app_version'] ?? $device->app_version;
            $device->os = $attributes['os'] ?? $device->os;
            $device->os_version = $attributes['os_version'] ?? $device->os_version;
        }

        $device->save();

        return $device;
    }
}
