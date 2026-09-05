<?php

namespace App\Services;

use App\Models\Changelog;
use App\Models\CountryTrafficSummary;
use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\Event;
use App\Models\HourlyTrafficSummary;
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
    public function __construct(private readonly CountryNameResolver $countryNames) {}

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
     * @return array{overview: int, versions: int, features: int, errors: int, devices: int, releases: int, tickets: int, changelog: int, traffic: int, geography: int, behavior: int}
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
            'traffic' => Event::where('occurred_at', '>=', now()->subDay())->count(),
            'geography' => Event::whereNotNull('country_code')->distinct('country_code')->count('country_code'),
            'behavior' => Device::where('last_seen_at', '<', now()->subDays(30))->count(),
        ];
    }

    /**
     * Reads from `hourly_traffic_summary` (Fase 3.2), not raw `events` —
     * the whole point of the summary table is that this never has to scan
     * the ever-growing events table for a chart that's shown on every visit.
     *
     * @return list<array{hour: int, total: int, intensity: float}>
     */
    public function hourlyTrafficHeatmap(int $days): array
    {
        $totals = HourlyTrafficSummary::query()
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('hour, SUM(event_count) as total')
            ->groupBy('hour')
            ->pluck('total', 'hour');

        $max = max([1, ...$totals->values()->all()]);

        return collect(range(0, 23))
            ->map(fn (int $hour): array => [
                'hour' => $hour,
                'total' => (int) ($totals[$hour] ?? 0),
                'intensity' => round((int) ($totals[$hour] ?? 0) / $max, 3),
            ])
            ->all();
    }

    /**
     * Also summary-backed: each day's total is the sum of its 24 hourly
     * rows, then bucketed by weekday in PHP so the query stays portable
     * across MySQL and sqlite (no DAYOFWEEK()/strftime() split needed).
     *
     * @return list<array{day: string, total: int, percentage: float}>
     */
    public function trafficByDayOfWeek(int $days): array
    {
        $dailyTotals = HourlyTrafficSummary::query()
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('date, SUM(event_count) as total')
            ->groupBy('date')
            ->get();

        $byWeekday = array_fill(1, 7, 0);

        foreach ($dailyTotals as $row) {
            $byWeekday[Carbon::parse($row->date)->dayOfWeekIso] += (int) $row->total;
        }

        $grandTotal = array_sum($byWeekday);
        $labels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        return collect($labels)
            ->map(fn (string $label, int $iso): array => [
                'day' => $label,
                'total' => $byWeekday[$iso],
                'percentage' => $grandTotal > 0 ? round($byWeekday[$iso] / $grandTotal * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Summary-backed (Fase 3.2): sums `country_traffic_summary` instead of
     * scanning raw events.
     *
     * @return list<array{country_code: string, country_name: string, total: int, percentage: float}>
     */
    public function geographicDistribution(int $days): array
    {
        $rows = CountryTrafficSummary::query()
            ->where('date', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('country_code, SUM(device_count) as total')
            ->groupBy('country_code')
            ->orderByDesc('total')
            ->get();

        $grandTotal = $rows->sum('total');

        return $rows
            ->map(fn ($row): array => [
                'country_code' => $row->country_code,
                'country_name' => $this->countryNames->name($row->country_code),
                'total' => (int) $row->total,
                'percentage' => $grandTotal > 0 ? round($row->total / $grandTotal * 100, 1) : 0.0,
            ])
            ->all();
    }

    /**
     * Daily device_count trend for the top-N countries by total volume over
     * the period — one line per country, so the chart shows who's actually
     * growing rather than just today's snapshot.
     *
     * @return array{labels: list<string>, series: list<array{code: string, name: string, data: list<int>}>}
     */
    public function countryGrowthTrend(int $days, int $topN = 5): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $topCountries = CountryTrafficSummary::query()
            ->where('date', '>=', $since->toDateString())
            ->selectRaw('country_code, SUM(device_count) as total')
            ->groupBy('country_code')
            ->orderByDesc('total')
            ->limit($topN)
            ->pluck('country_code');

        $rows = CountryTrafficSummary::query()
            ->where('date', '>=', $since->toDateString())
            ->whereIn('country_code', $topCountries)
            ->get()
            ->groupBy('country_code');

        $labels = [];
        for ($i = 0; $i < $days; $i++) {
            $labels[] = $since->copy()->addDays($i)->toDateString();
        }

        $series = $topCountries
            ->map(function (string $code) use ($rows, $labels): array {
                $byDate = ($rows->get($code) ?? collect())->keyBy(fn ($row): string => $row->date->toDateString());

                return [
                    'code' => $code,
                    'name' => $this->countryNames->name($code),
                    'data' => collect($labels)->map(fn (string $date): int => (int) ($byDate->get($date)?->device_count ?? 0))->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'labels' => collect($labels)->map(fn (string $date): string => Carbon::parse($date)->format('d M'))->all(),
            'series' => $series,
        ];
    }

    /**
     * Histogram of completed session durations. A portable CASE WHEN keeps
     * the bucketing in SQL (cheap even as device_sessions grows) without
     * relying on a MySQL-only function.
     *
     * @return list<array{label: string, count: int}>
     */
    public function sessionLengthDistribution(int $days): array
    {
        $buckets = [
            'lt_1m' => ['label' => '< 1m', 'condition' => 'duration_seconds < 60'],
            'm1_5' => ['label' => '1–5m', 'condition' => 'duration_seconds >= 60 AND duration_seconds < 300'],
            'm5_15' => ['label' => '5–15m', 'condition' => 'duration_seconds >= 300 AND duration_seconds < 900'],
            'm15_30' => ['label' => '15–30m', 'condition' => 'duration_seconds >= 900 AND duration_seconds < 1800'],
            'm30_60' => ['label' => '30–60m', 'condition' => 'duration_seconds >= 1800 AND duration_seconds < 3600'],
            'h1_2' => ['label' => '1–2h', 'condition' => 'duration_seconds >= 3600 AND duration_seconds < 7200'],
            'h2_plus' => ['label' => '2h+', 'condition' => 'duration_seconds >= 7200'],
        ];

        $selects = collect($buckets)
            ->map(fn (array $bucket, string $alias): string => "SUM(CASE WHEN {$bucket['condition']} THEN 1 ELSE 0 END) as {$alias}")
            ->implode(', ');

        $counts = DeviceSession::query()
            ->whereNotNull('duration_seconds')
            ->where('started_at', '>=', now()->subDays($days))
            ->selectRaw($selects)
            ->first();

        return collect($buckets)
            ->map(fn (array $bucket, string $alias): array => [
                'label' => $bucket['label'],
                'count' => (int) ($counts->{$alias} ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * A device counts as "new" the day its first_seen_at falls on, and
     * "returning" any later day it has activity — the join's date compare
     * uses DATE(), which (unlike HOUR()) is portable across MySQL/sqlite.
     *
     * @return list<array{date: string, new: int, returning: int}>
     */
    public function newVsReturningDevices(int $days): array
    {
        $since = now()->subDays($days - 1)->startOfDay();

        $newCounts = Device::query()
            ->where('first_seen_at', '>=', $since)
            ->selectRaw('DATE(first_seen_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $returningCounts = Event::query()
            ->join('devices', 'devices.id', '=', 'events.device_id')
            ->where('events.occurred_at', '>=', $since)
            ->whereRaw('DATE(devices.first_seen_at) < DATE(events.occurred_at)')
            ->selectRaw('DATE(events.occurred_at) as day, COUNT(DISTINCT events.device_id) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $trend = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $since->copy()->addDays($i)->toDateString();
            $trend[] = [
                'date' => $date,
                'new' => (int) ($newCounts[$date] ?? 0),
                'returning' => (int) ($returningCounts[$date] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * Weekly-cohort retention: of the devices first seen in a given week,
     * what share still had activity N weeks later. Bounded to a handful of
     * cohorts/offsets deliberately — this is an internal dashboard read by
     * a few maintainers, not a query that needs to scale past that.
     *
     * @return array{cohorts: list<array{label: string, size: int, retention: list<float|null>}>, maxOffset: int}
     */
    public function cohortRetention(int $cohortWeeks = 6): array
    {
        $now = now();
        $cohorts = [];

        for ($w = $cohortWeeks - 1; $w >= 0; $w--) {
            $weekStart = $now->copy()->subWeeks($w)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();

            if ($weekStart->gt($now)) {
                continue;
            }

            $deviceIds = Device::query()
                ->whereBetween('first_seen_at', [$weekStart, $weekEnd])
                ->pluck('id');

            $maxOffset = (int) $weekStart->diffInWeeks($now);
            $retention = [];

            for ($offset = 0; $offset <= min($maxOffset, $cohortWeeks - 1); $offset++) {
                $offsetStart = $weekStart->copy()->addWeeks($offset);
                $offsetEnd = $offsetStart->copy()->endOfWeek();

                if ($deviceIds->isEmpty()) {
                    $retention[] = null;

                    continue;
                }

                $active = Event::query()
                    ->whereIn('device_id', $deviceIds)
                    ->whereBetween('occurred_at', [$offsetStart, $offsetEnd])
                    ->distinct('device_id')
                    ->count('device_id');

                $retention[] = round($active / $deviceIds->count() * 100, 1);
            }

            $cohorts[] = [
                'label' => $weekStart->format('d M'),
                'size' => $deviceIds->count(),
                'retention' => $retention,
            ];
        }

        return [
            'cohorts' => $cohorts,
            'maxOffset' => $cohortWeeks - 1,
        ];
    }

    /**
     * Devices that have gone quiet for longer than the threshold — a proxy
     * for "probably uninstalled" since there's no explicit uninstall event.
     *
     * @return array{count: int, percentage: float, threshold_days: int, devices: Collection<int, Device>}
     */
    public function churnSummary(int $thresholdDays = 30, int $limit = 20): array
    {
        $total = Device::count();
        $cutoff = now()->subDays($thresholdDays);

        $query = Device::query()->where('last_seen_at', '<', $cutoff);
        $count = $query->count();

        return [
            'count' => $count,
            'percentage' => $total > 0 ? round($count / $total * 100, 1) : 0.0,
            'threshold_days' => $thresholdDays,
            // Ascending: the oldest last_seen_at is the longest-silent device.
            'devices' => (clone $query)->orderBy('last_seen_at', 'asc')->limit($limit)->get(),
        ];
    }

    /**
     * A device's exact os/os_version combo — the "Windows 11 23H2 vs
     * Windows 10 22H2" granularity needed for compatibility/testing
     * decisions, distinct from the coarser os-only breakdown on Overview.
     *
     * @return list<array{label: string, count: int, percentage: float}>
     */
    public function osVersionBreakdown(): array
    {
        $total = Device::count();

        if ($total === 0) {
            return [];
        }

        return Device::query()
            ->selectRaw('os, os_version, COUNT(*) as total')
            ->groupBy('os', 'os_version')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'label' => trim(($row->os ?? 'Unknown OS').' '.($row->os_version ?? '')),
                'count' => (int) $row->total,
                'percentage' => round($row->total / $total * 100, 1),
            ])
            ->all();
    }

    /**
     * Most common PHP+MySQL/MariaDB version pairs, read from the
     * `service_started` event's payload metadata — this is only as good as
     * what the desktop client actually reports in that payload.
     *
     * @return list<array{label: string, count: int, percentage: float}>
     */
    public function topStackCombos(int $days, int $limit = 10): array
    {
        $rows = Event::query()
            ->where('event_type', 'service.start')
            ->where('occurred_at', '>=', now()->subDays($days))
            ->whereNotNull('payload')
            ->get(['payload']);

        $combos = $rows
            ->map(function ($row): ?string {
                $php = $row->payload['php_version'] ?? null;
                $db = $row->payload['mysql_version'] ?? $row->payload['mariadb_version'] ?? null;

                return $php && $db ? "PHP {$php} + {$db}" : null;
            })
            ->filter()
            ->countBy();

        $total = $combos->sum();

        if ($total === 0) {
            return [];
        }

        return $combos
            ->sortDesc()
            ->take($limit)
            ->map(fn (int $count, string $label): array => [
                'label' => $label,
                'count' => $count,
                'percentage' => round($count / $total * 100, 1),
            ])
            ->values()
            ->all();
    }

    /**
     * Install -> active-after-7-days funnel (Fase 3.6). There's no
     * "download" telemetry in this API at all — a device only exists here
     * once it has opened the app — so that stage is reported as untracked
     * rather than backfilled with a guess.
     *
     * The 7-day stage is measured only against devices old enough to have
     * had the chance to reach day 7 ("eligible"); a device installed
     * yesterday isn't a drop-off yet, it just hasn't had time to return.
     *
     * @return array{stages: list<array{label: string, count: ?int, percentage: ?float, tracked: bool}>, eligible_for_7_day_measurement: int}
     */
    public function productFunnel(): array
    {
        $totalInstalls = Device::count();
        $cutoff = now()->subDays(7);

        $eligibleDevices = Device::query()
            ->where('first_seen_at', '<=', $cutoff)
            ->get(['id', 'first_seen_at']);

        $activeAfter7Days = 0;

        if ($eligibleDevices->isNotEmpty()) {
            $lastEventPerDevice = Event::query()
                ->whereIn('device_id', $eligibleDevices->pluck('id'))
                ->selectRaw('device_id, MAX(occurred_at) as last_event_at')
                ->groupBy('device_id')
                ->pluck('last_event_at', 'device_id');

            $activeAfter7Days = $eligibleDevices
                ->filter(function ($device) use ($lastEventPerDevice): bool {
                    $lastEventAt = $lastEventPerDevice->get($device->id);

                    return $lastEventAt && Carbon::parse($lastEventAt)->gte($device->first_seen_at->copy()->addDays(7));
                })
                ->count();
        }

        return [
            'stages' => [
                ['label' => 'Download', 'count' => null, 'percentage' => null, 'tracked' => false],
                ['label' => 'Install (first app_opened)', 'count' => $totalInstalls, 'percentage' => 100.0, 'tracked' => true],
                [
                    'label' => 'Active after 7 days',
                    'count' => $activeAfter7Days,
                    'percentage' => $eligibleDevices->count() > 0 ? round($activeAfter7Days / $eligibleDevices->count() * 100, 1) : null,
                    'tracked' => true,
                ],
            ],
            'eligible_for_7_day_measurement' => $eligibleDevices->count(),
        ];
    }

    /**
     * Aggregate-only numbers safe for an unauthenticated public endpoint
     * (Fase 3.7) — counts, nothing granular (no per-country, per-version,
     * or per-device breakdowns) so this can't be used to fingerprint a
     * single user or hand a competitor a detailed usage profile.
     *
     * @return array{active_devices: array{daily: int, weekly: int, monthly: int}, total_devices: int, generated_at: string}
     */
    public function publicAggregateStats(): array
    {
        $now = now();

        return [
            'active_devices' => [
                'daily' => $this->distinctActiveDevices($now->copy()->subDay(), $now),
                'weekly' => $this->distinctActiveDevices($now->copy()->subWeek(), $now),
                'monthly' => $this->distinctActiveDevices($now->copy()->subMonth(), $now),
            ],
            'total_devices' => Device::count(),
            'generated_at' => $now->toIso8601String(),
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
