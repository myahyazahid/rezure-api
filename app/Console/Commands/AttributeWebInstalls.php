<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\WebEvent;
use App\Models\WebVisitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;

/**
 * Links new desktop installs to the website visitor whose download most
 * likely produced them (v4, Fase 4.12): same IP — the device's
 * first_seen_ip vs the download's — within the attribution window before
 * the install was first seen. Probabilistic by nature (shared NAT IPs can
 * mismatch), which the Conversion page says out loud.
 */
#[Signature('app:attribute-web-installs {--days=14 : How far back to look for unattributed installs}')]
#[Description('Attribute new desktop installs to website visitors by download IP')]
class AttributeWebInstalls extends Command
{
    public function handle(): int
    {
        $windowHours = config('web-analytics.attribution_window_hours');
        $linked = 0;

        Device::query()
            ->whereNotNull('first_seen_ip')
            ->where('first_seen_at', '>=', now()->subDays((int) $this->option('days')))
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')->from('web_visitors')->whereColumn('web_visitors.device_id', 'devices.id'))
            ->orderBy('first_seen_at')
            ->each(function (Device $device) use ($windowHours, &$linked): void {
                $download = WebEvent::query()
                    ->where('name', WebEvent::DOWNLOAD)
                    ->where('ip_address', $device->first_seen_ip)
                    ->whereNotNull('web_visitor_id')
                    ->whereBetween('occurred_at', [$device->first_seen_at->copy()->subHours($windowHours), $device->first_seen_at])
                    ->latest('occurred_at')
                    ->first();

                if ($download === null) {
                    return;
                }

                // One install per visitor: a visitor already linked keeps
                // its first match.
                $linked += WebVisitor::query()
                    ->whereKey($download->web_visitor_id)
                    ->whereNull('device_id')
                    ->update(['device_id' => $device->id, 'device_linked_at' => now()]);
            });

        $this->info("Linked {$linked} installs to website visitors.");

        return self::SUCCESS;
    }
}
