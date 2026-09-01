<?php

namespace App\Services;

use App\Models\Changelog;
use App\Models\Device;
use App\Models\Event;
use App\Models\Release;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregation queries backing the dashboard. Kept out of the controllers so
 * the controllers stay thin and the queries stay in one reviewable place —
 * every query here is expected to hit the indexes added alongside the
 * devices/events/device_sessions migrations.
 */
class DashboardMetricsService
{
    /**
     * @return array{dau: int, dau_change: ?float, wau: int, wau_change: ?float, mau: int, mau_change: ?float, stickiness: ?float}
     */
    public function activeUserSummary(): array
    {
        $now = now();

        $dau = $this->distinctActiveDevices($now->copy()->subDay(), $now);
        $dauPrev = $this->distinctActiveDevices($now->copy()->subDays(2), $now->copy()->subDay());

        $wau = $this->distinctActiveDevices($now->copy()->subWeek(), $now);
        $wauPrev = $this->distinctActiveDevices($now->copy()->subWeeks(2), $now->copy()->subWeek());

        $mau = $this->distinctActiveDevices($now->copy()->subMonth(), $now);
        $mauPrev = $this->distinctActiveDevices($now->copy()->subMonths(2), $now->copy()->subMonth());

        return [
            'dau' => $dau,
            'dau_change' => $this->percentChange($dau, $dauPrev),
            'wau' => $wau,
            'wau_change' => $this->percentChange($wau, $wauPrev),
            'mau' => $mau,
            'mau_change' => $this->percentChange($mau, $mauPrev),
            'stickiness' => $mau > 0 ? round($dau / $mau * 100, 1) : null,
        ];
    }

    /**
     * @return array{total: int, new_in_period: int, new_change: ?float}
     */
    public function registeredDevices(int $periodDays): array
    {
        $now = now();
        $total = Device::count();

        $newInPeriod = Device::where('first_seen_at', '>=', $now->copy()->subDays($periodDays))->count();
        $newInPrevPeriod = Device::whereBetween('first_seen_at', [
            $now->copy()->subDays($periodDays * 2),
            $now->copy()->subDays($periodDays),
        ])->count();

        return [
            'total' => $total,
            'new_in_period' => $newInPeriod,
            'new_change' => $this->percentChange($newInPeriod, $newInPrevPeriod),
        ];
    }

    /**
     * Daily unique device_id counts for the trend chart.
     *
     * @return list<array{date: string, count: int}>
     */
    public function activeDevicesTrend(int $periodDays): array
    {
        $since = now()->subDays($periodDays - 1)->startOfDay();

        $rows = Event::query()
            ->selectRaw('DATE(occurred_at) as day, COUNT(DISTINCT device_id) as total')
            ->where('occurred_at', '>=', $since)
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $trend = [];
        for ($i = 0; $i < $periodDays; $i++) {
            $date = $since->copy()->addDays($i)->toDateString();
            $trend[] = [
                'date' => $date,
                'count' => (int) ($rows->get($date)->total ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * @return list<array{os: string, count: int, percentage: float}>
     */
    public function osBreakdown(): array
    {
        $total = Device::count();

        if ($total === 0) {
            return [];
        }

        return Device::query()
            ->selectRaw('os, COUNT(*) as total')
            ->groupBy('os')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'os' => $row->os ?? 'Other / unknown',
                'count' => (int) $row->total,
                'percentage' => round($row->total / $total * 100, 1),
            ])
            ->all();
    }

    /**
     * @return Collection<int, Event>
     */
    public function recentIngest(int $limit = 8): Collection
    {
        return Event::with('device')
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{healthy: bool, last_event_at: ?Carbon}
     */
    public function ingestHealth(): array
    {
        $lastEvent = Event::orderByDesc('occurred_at')->first();

        return [
            'healthy' => $lastEvent !== null && $lastEvent->occurred_at->gt(now()->subMinutes(15)),
            'last_event_at' => $lastEvent?->occurred_at,
        ];
    }

    /**
     * Version adoption by each device's currently-reported app_version —
     * this answers "who's still on an old build", not "how many events came
     * from an old build".
     *
     * @return list<array{version: string, count: int, percentage: float}>
     */
    public function versionAdoption(): array
    {
        $total = Device::count();

        if ($total === 0) {
            return [];
        }

        return Device::query()
            ->selectRaw('app_version, COUNT(*) as total')
            ->groupBy('app_version')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'version' => $row->app_version ?? 'unknown',
                'count' => (int) $row->total,
                'percentage' => round($row->total / $total * 100, 1),
            ])
            ->all();
    }

    /**
     * @return list<array{event_type: string, event_name: ?string, count: int, percentage: float}>
     */
    public function featureUsage(int $periodDays, int $limit = 20): array
    {
        $since = now()->subDays($periodDays);

        $total = Event::where('event_type', '!=', 'error.report')
            ->where('occurred_at', '>=', $since)
            ->count();

        if ($total === 0) {
            return [];
        }

        return Event::query()
            ->selectRaw('event_type, event_name, COUNT(*) as total')
            ->where('event_type', '!=', 'error.report')
            ->where('occurred_at', '>=', $since)
            ->groupBy('event_type', 'event_name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'event_type' => $row->event_type,
                'event_name' => $row->event_name,
                'count' => (int) $row->total,
                'percentage' => round($row->total / $total * 100, 1),
            ])
            ->all();
    }

    /**
     * @return list<array{error: string, occurrences: int, affected_devices: int, last_seen_at: Carbon}>
     */
    public function errorReports(int $periodDays): array
    {
        $since = now()->subDays($periodDays);

        return Event::query()
            ->selectRaw('event_name, COUNT(*) as total, COUNT(DISTINCT device_id) as devices, MAX(occurred_at) as last_seen')
            ->where('event_type', 'error.report')
            ->where('occurred_at', '>=', $since)
            ->groupBy('event_name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'error' => $row->event_name ?? 'Unknown error',
                'occurrences' => (int) $row->total,
                'affected_devices' => (int) $row->devices,
                'last_seen_at' => Carbon::parse($row->last_seen),
            ])
            ->all();
    }

    public function paginatedDevices(?string $versionFilter = null, int $perPage = 25): LengthAwarePaginator
    {
        return $this->devicesQuery($versionFilter)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Builder<Device>
     */
    public function devicesQuery(?string $versionFilter = null): Builder
    {
        return Device::query()
            ->when($versionFilter, fn ($query) => $query->where('app_version', $versionFilter))
            ->orderByDesc('last_seen_at');
    }

    /**
     * @return list<string>
     */
    public function knownVersions(): array
    {
        return Device::query()
            ->select('app_version')
            ->distinct()
            ->orderByDesc('app_version')
            ->pluck('app_version')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Sidebar badge counts. Deliberately cheap (single-column counts, no
     * joins) since this runs on every dashboard page load via the layout's
     * view composer.
     *
     * @return array{overview: int, versions: int, features: int, errors: int, devices: int, releases: int, tickets: int, changelog: int}
     */
    public function navigationBadges(): array
    {
        $since = now()->subDays(30);

        return [
            'overview' => Event::where('occurred_at', '>=', now()->subDay())->distinct('device_id')->count('device_id'),
            'versions' => Device::query()->distinct()->count('app_version'),
            'features' => Event::where('event_type', '!=', 'error.report')
                ->where('occurred_at', '>=', $since)
                ->select('event_type', 'event_name')
                ->distinct()
                ->get()
                ->count(),
            'errors' => Event::where('event_type', 'error.report')
                ->where('occurred_at', '>=', $since)
                ->distinct()
                ->count('event_name'),
            'devices' => Device::count(),
            'releases' => Release::count(),
            'tickets' => Ticket::where('status', 'open')->count(),
            'changelog' => Changelog::count(),
        ];
    }

    private function distinctActiveDevices(Carbon $from, Carbon $to): int
    {
        return Event::whereBetween('occurred_at', [$from, $to])->distinct('device_id')->count('device_id');
    }

    private function percentChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }
}
