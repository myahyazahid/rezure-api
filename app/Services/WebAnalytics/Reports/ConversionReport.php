<?php

namespace App\Services\WebAnalytics\Reports;

use App\Models\Device;
use App\Models\GithubReleaseDownload;
use App\Models\WebEvent;
use App\Models\WebFunnel;
use App\Models\WebGoal;
use App\Models\WebPageview;
use App\Models\WebSession;
use App\Models\WebVisitor;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use App\Services\WebAnalytics\WebDimensions;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Website › Events, Goals, Funnels and Conversion (website → desktop app).
 */
class ConversionReport
{
    /**
     * Cap on rows read per funnel step — funnels are matched in PHP.
     */
    private const FUNNEL_ROW_LIMIT = 50000;

    public function __construct(private readonly WebAnalyticsQuery $query) {}

    /**
     * @return list<array{name: string, total: int, visitors: int, sessions: int, per_session: float}>
     */
    public function events(WebAnalyticsFilters $filters): array
    {
        return $this->query->remember($filters, 'events', fn (): array => $this->query->events($filters)
            ->toBase()
            ->selectRaw('web_events.name as name, COUNT(*) as total, COUNT(DISTINCT web_events.web_visitor_id) as visitors, COUNT(DISTINCT web_events.web_session_id) as sessions')
            ->groupBy('web_events.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn (object $row): array => [
                'name' => $row->name,
                'total' => (int) $row->total,
                'visitors' => (int) $row->visitors,
                'sessions' => (int) $row->sessions,
                'per_session' => $row->sessions > 0 ? round($row->total / $row->sessions, 2) : 0.0,
            ])
            ->all());
    }

    /**
     * One event's labels and the values of each of its props.
     *
     * @return array{labels: list<array{label: string, total: int, visitors: int}>, props: array<string, list<array{value: string, total: int}>>}
     */
    public function eventDetail(WebAnalyticsFilters $filters, string $name): array
    {
        $labels = $this->query->events($filters, $name)
            ->toBase()
            ->selectRaw("COALESCE(web_events.label, '".WebAnalyticsQuery::NONE."') as label, COUNT(*) as total, COUNT(DISTINCT web_events.web_visitor_id) as visitors")
            ->groupByRaw("COALESCE(web_events.label, '".WebAnalyticsQuery::NONE."')")
            ->orderByDesc('total')
            ->limit(50)
            ->get()
            ->map(fn (object $row): array => ['label' => $row->label, 'total' => (int) $row->total, 'visitors' => (int) $row->visitors])
            ->all();

        // Props are free-form JSON, so they're tallied in PHP over a sample
        // rather than with driver-specific JSON SQL.
        $props = [];

        $this->query->events($filters, $name)
            ->whereNotNull('web_events.props')
            ->latest('web_events.occurred_at')
            ->limit(5000)
            ->pluck('web_events.props')
            ->each(function (mixed $value) use (&$props): void {
                $decoded = is_array($value) ? $value : json_decode((string) $value, true);

                foreach ((array) $decoded as $key => $propValue) {
                    $propValue = is_bool($propValue) ? ($propValue ? 'true' : 'false') : (string) $propValue;
                    $props[$key][$propValue] = ($props[$key][$propValue] ?? 0) + 1;
                }
            });

        return [
            'labels' => $labels,
            'props' => array_map(function (array $values): array {
                arsort($values);

                return array_map(fn (string $value, int $total): array => ['value' => $value, 'total' => $total], array_keys(array_slice($values, 0, 10, true)), array_slice($values, 0, 10, true));
            }, $props),
        ];
    }

    /**
     * Every goal with its conversions (sessions that completed it) and rate.
     *
     * @return list<array{goal: WebGoal, conversions: int, visitors: int, rate: float}>
     */
    public function goals(WebAnalyticsFilters $filters): array
    {
        $sessions = max(1, $this->query->sessions($filters)->count());

        return WebGoal::query()->orderBy('name')->get()->map(function (WebGoal $goal) use ($filters, $sessions): array {
            $converted = $this->query->sessions($filters)
                ->whereExists(fn (QueryBuilder $sub) => $this->query->goalConversions($sub, $goal, 'web_sessions.id'))
                ->toBase()
                ->selectRaw('COUNT(*) as sessions, COUNT(DISTINCT web_visitor_id) as visitors')
                ->first();

            return [
                'goal' => $goal,
                'conversions' => (int) $converted->sessions,
                'visitors' => (int) $converted->visitors,
                'rate' => round($converted->sessions / $sessions * 100, 2),
            ];
        })->all();
    }

    /**
     * A goal's conversion rate per value of one dimension.
     *
     * @return list<array{value: string, sessions: int, conversions: int, rate: float}>
     */
    public function goalBreakdown(WebAnalyticsFilters $filters, WebGoal $goal, string $dimension): array
    {
        $value = 'COALESCE('.WebDimensions::expression($dimension).", '".WebAnalyticsQuery::NONE."')";
        $converted = '(CASE WHEN EXISTS ('.$this->goalExistsSql($goal).') THEN 1 ELSE 0 END)';

        return $this->query->sessions($filters)
            ->toBase()
            ->selectRaw("{$value} as value, COUNT(*) as sessions, SUM({$converted}) as conversions", $this->goalExistsBindings($goal))
            ->groupByRaw($value)
            ->orderByDesc('conversions')
            ->limit(15)
            ->get()
            ->map(fn (object $row): array => [
                'value' => (string) $row->value,
                'sessions' => (int) $row->sessions,
                'conversions' => (int) $row->conversions,
                'rate' => $row->sessions > 0 ? round($row->conversions / $row->sessions * 100, 2) : 0.0,
            ])
            ->all();
    }

    /**
     * Runs a funnel over the period. Steps must happen in order: step N
     * counts only if it happened after the step N-1 that counted. With
     * `visitor` scope the steps may span sessions, within window_hours of
     * the first step.
     *
     * @param  list<array{type: string, match: string, label?: ?string}>  $steps
     * @return array{steps: list<array{label: string, count: int, conversion: float, drop_off: float, median_seconds: ?int}>, entered: int, completed: int, breakdown: list<array{value: string, entered: int, completed: int, rate: float}>}
     */
    public function funnel(WebAnalyticsFilters $filters, array $steps, string $scope = WebFunnel::SCOPE_SESSION, int $windowHours = 24, ?string $breakdown = null): array
    {
        $key = $scope === WebFunnel::SCOPE_VISITOR ? 'web_visitor_id' : 'web_session_id';
        $occurrences = [];

        foreach ($steps as $index => $step) {
            $occurrences[$index] = $this->stepOccurrences($filters, $step, $key, $scope === WebFunnel::SCOPE_VISITOR ? $windowHours : 0);
        }

        $counts = array_fill(0, count($steps), 0);
        $durations = array_fill(0, count($steps), []);
        $completedKeys = [];
        $enteredKeys = [];

        foreach ($occurrences[0] ?? [] as $id => $times) {
            $start = $times[0];
            $previous = $start;
            $counts[0]++;
            $enteredKeys[] = $id;
            $reached = 1;

            for ($index = 1; $index < count($steps); $index++) {
                $next = $this->firstAfter($occurrences[$index][$id] ?? [], $previous);

                if ($next === null || ($scope === WebFunnel::SCOPE_VISITOR && $next - $start > $windowHours * 3600)) {
                    break;
                }

                $counts[$index]++;
                $durations[$index][] = $next - $previous;
                $previous = $next;
                $reached++;
            }

            if ($reached === count($steps)) {
                $completedKeys[] = $id;
            }
        }

        $result = [];

        foreach ($steps as $index => $step) {
            $result[] = [
                'label' => $this->stepLabel($step),
                'count' => $counts[$index],
                'conversion' => $counts[0] > 0 ? round($counts[$index] / $counts[0] * 100, 1) : 0.0,
                'drop_off' => $index > 0 && $counts[$index - 1] > 0 ? round(($counts[$index - 1] - $counts[$index]) / $counts[$index - 1] * 100, 1) : 0.0,
                'median_seconds' => $durations[$index] !== [] ? (int) WebAnalyticsQuery::percentile($durations[$index], 50) : null,
            ];
        }

        return [
            'steps' => $result,
            'entered' => $counts[0] ?? 0,
            'completed' => count($completedKeys),
            'breakdown' => $breakdown !== null && $scope === WebFunnel::SCOPE_SESSION
                ? $this->funnelBreakdown($enteredKeys, $completedKeys, $breakdown)
                : [],
        ];
    }

    /**
     * Website visit → download → install → still active a week later.
     *
     * @return array<string, mixed>
     */
    public function crossProduct(WebAnalyticsFilters $filters): array
    {
        return $this->query->remember($filters, 'cross-product', function () use ($filters): array {
            $visitors = $this->query->visitorCount($filters);
            $downloadPageVisitors = (int) $this->query->pageviews($filters)
                ->toBase()
                ->where('web_pageviews.path', '/download')
                ->selectRaw('COUNT(DISTINCT web_pageviews.web_visitor_id) as visitors')
                ->value('visitors');
            $downloads = $this->query->events($filters, WebEvent::DOWNLOAD);
            $downloadCount = (clone $downloads)->count();
            $downloadVisitors = (int) (clone $downloads)->toBase()->selectRaw('COUNT(DISTINCT web_events.web_visitor_id) as visitors')->value('visitors');

            $devices = Device::query()->whereBetween('first_seen_at', [$filters->fromUtc(), $filters->toUtc()])->get(['id', 'first_seen_at', 'last_seen_at']);
            $eligible = $devices->filter(fn (Device $device): bool => $device->first_seen_at->lte(now()->subDays(7)));
            $active = $eligible->filter(fn (Device $device): bool => $device->last_seen_at?->gte($device->first_seen_at->copy()->addDays(7)) ?? false);

            $attributed = WebVisitor::query()
                ->whereNotNull('device_id')
                ->whereHas('device', fn ($query) => $query->whereBetween('first_seen_at', [$filters->fromUtc(), $filters->toUtc()]))
                ->with('device:id,first_seen_at,last_seen_at')
                ->get(['id', 'device_id', 'first_channel', 'first_source']);

            $attributedActive = $attributed->filter(fn (WebVisitor $visitor): bool => $visitor->device->first_seen_at->lte(now()->subDays(7))
                && $visitor->device->last_seen_at?->gte($visitor->device->first_seen_at->copy()->addDays(7)));

            return [
                'stages' => [
                    ['label' => 'Website visitors', 'count' => $visitors],
                    ['label' => 'Visited /download', 'count' => $downloadPageVisitors],
                    ['label' => 'Downloaded (website)', 'count' => $downloadVisitors],
                    ['label' => 'Installed (new devices)', 'count' => $devices->count()],
                    ['label' => 'Active after 7 days', 'count' => $active->count()],
                ],
                'downloads' => $downloadCount,
                'eligibleDevices' => $eligible->count(),
                'attributed' => $attributed->count(),
                'attributedActive' => $attributedActive->count(),
                'attributedChannels' => $attributed->groupBy(fn (WebVisitor $visitor): string => $visitor->first_channel ?? WebAnalyticsQuery::NONE)
                    ->map(fn (Collection $group, string $channel): array => [
                        'channel' => $channel,
                        'installs' => $group->count(),
                        'active' => $group->intersectByKeys($attributedActive)->count(),
                    ])
                    ->sortByDesc('installs')
                    ->values()
                    ->all(),
                'byVersion' => (clone $downloads)->toBase()
                    ->selectRaw("COALESCE(web_events.label, '".WebAnalyticsQuery::NONE."') as version, COUNT(*) as total")
                    ->groupByRaw("COALESCE(web_events.label, '".WebAnalyticsQuery::NONE."')")
                    ->orderByDesc('total')
                    ->get()
                    ->map(fn (object $row): array => ['version' => $row->version, 'total' => (int) $row->total])
                    ->all(),
                'byChannel' => $this->query->breakdown($filters, 'channel', 10),
                'github' => $this->githubDownloads($filters),
            ];
        });
    }

    /**
     * Downloads GitHub counted in the period (difference between the daily
     * snapshots at both ends), per version.
     *
     * @return array{total: ?int, versions: list<array{version: string, downloads: int, all_time: int}>}
     */
    private function githubDownloads(WebAnalyticsFilters $filters): array
    {
        $last = GithubReleaseDownload::query()->where('date', '<=', $filters->to->toDateString())->max('date');

        if ($last === null) {
            return ['total' => null, 'versions' => []];
        }

        $first = GithubReleaseDownload::query()->where('date', '<', $filters->from->toDateString())->max('date');

        $end = GithubReleaseDownload::query()->where('date', $last)->get()->groupBy('version');
        $start = $first !== null ? GithubReleaseDownload::query()->where('date', $first)->get()->groupBy('version') : collect();

        $versions = $end->map(fn (Collection $assets, string $version): array => [
            'version' => $version ?: '?',
            'downloads' => max(0, (int) $assets->sum('download_count') - (int) ($start->get($version)?->sum('download_count') ?? 0)),
            'all_time' => (int) $assets->sum('download_count'),
        ])->sortByDesc('all_time')->values()->all();

        return ['total' => array_sum(array_column($versions, 'downloads')), 'versions' => $versions];
    }

    /**
     * Each step's matches as `key => sorted unix timestamps`, keyed by
     * session or visitor.
     *
     * @param  array{type: string, match: string, label?: ?string}  $step
     * @return array<int, list<int>>
     */
    private function stepOccurrences(WebAnalyticsFilters $filters, array $step, string $key, int $extendHours): array
    {
        $to = $filters->toUtc()->copy()->addHours($extendHours);
        $inScope = $key === 'web_visitor_id'
            ? $this->query->sessions($filters)->select('web_visitor_id')
            : $this->query->sessions($filters)->select('web_sessions.id');

        $rows = $step['type'] === 'event'
            ? WebEvent::query()
                ->whereIn($key, $inScope)
                ->where('name', $step['match'])
                ->when($step['label'] ?? null, fn ($query, string $label) => $query->whereRaw("label LIKE ? ESCAPE '".WebGoal::LIKE_ESCAPE."'", [WebGoal::wildcardToLike($label)]))
            : WebPageview::query()
                ->whereIn($key, $inScope)
                ->tap(fn ($query) => $this->query->wherePathMatches($query, 'path', $step['match']));

        $occurrences = [];

        $rows->whereBetween('occurred_at', [$filters->fromUtc(), $to])
            ->limit(self::FUNNEL_ROW_LIMIT)
            ->toBase()
            ->get([$key.' as owner', 'occurred_at'])
            ->each(function (object $row) use (&$occurrences): void {
                if ($row->owner !== null) {
                    $occurrences[(int) $row->owner][] = strtotime($row->occurred_at.' UTC');
                }
            });

        foreach ($occurrences as &$times) {
            sort($times);
        }

        return $occurrences;
    }

    /**
     * @param  list<int>  $times
     */
    private function firstAfter(array $times, int $after): ?int
    {
        foreach ($times as $time) {
            if ($time >= $after) {
                return $time;
            }
        }

        return null;
    }

    /**
     * @param  list<int>  $entered
     * @param  list<int>  $completed
     * @return list<array{value: string, entered: int, completed: int, rate: float}>
     */
    private function funnelBreakdown(array $entered, array $completed, string $dimension): array
    {
        if ($entered === []) {
            return [];
        }

        $value = 'COALESCE('.WebDimensions::expression($dimension).", '".WebAnalyticsQuery::NONE."')";
        $values = [];

        foreach (array_chunk($entered, 1000) as $chunk) {
            WebSession::query()
                ->whereIn('id', $chunk)
                ->toBase()
                ->selectRaw("id, {$value} as value")
                ->get()
                ->each(function (object $row) use (&$values): void {
                    $values[(int) $row->id] = (string) $row->value;
                });
        }

        $completed = array_flip($completed);
        $groups = [];

        foreach ($values as $id => $groupValue) {
            $groups[$groupValue]['entered'] = ($groups[$groupValue]['entered'] ?? 0) + 1;
            $groups[$groupValue]['completed'] = ($groups[$groupValue]['completed'] ?? 0) + (isset($completed[$id]) ? 1 : 0);
        }

        uasort($groups, fn (array $a, array $b): int => $b['entered'] <=> $a['entered']);

        return array_map(fn (string $groupValue, array $group): array => [
            'value' => $groupValue,
            'entered' => $group['entered'],
            'completed' => $group['completed'],
            'rate' => round($group['completed'] / $group['entered'] * 100, 1),
        ], array_keys(array_slice($groups, 0, 15, true)), array_slice($groups, 0, 15, true));
    }

    /**
     * @param  array{type: string, match: string, label?: ?string}  $step
     */
    public function stepLabel(array $step): string
    {
        return $step['type'] === 'event'
            ? 'Event: '.$step['match'].(($step['label'] ?? null) ? ' ('.$step['label'].')' : '')
            : 'Visit: '.$step['match'];
    }

    private function goalExistsSql(WebGoal $goal): string
    {
        return $this->query->goalConversions(DB::query(), $goal, 'web_sessions.id')->toSql();
    }

    /**
     * @return list<mixed>
     */
    private function goalExistsBindings(WebGoal $goal): array
    {
        return $this->query->goalConversions(DB::query(), $goal, 'web_sessions.id')->getBindings();
    }
}
