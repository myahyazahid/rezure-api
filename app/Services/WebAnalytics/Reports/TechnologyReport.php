<?php

namespace App\Services\WebAnalytics\Reports;

use App\Models\WebBotHitSummary;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;

/**
 * Website › Technology: browsers, devices, screens and hardware — plus the
 * bot traffic that ingest dropped.
 */
class TechnologyReport
{
    /**
     * @var list<string>
     */
    public const BREAKDOWNS = [
        'browser', 'browser_version', 'browser_engine', 'os', 'os_version', 'device_type',
        'device_brand', 'device_model', 'screen', 'viewport', 'connection',
    ];

    public function __construct(private readonly WebAnalyticsQuery $query) {}

    /**
     * @return array<string, mixed>
     */
    public function build(WebAnalyticsFilters $filters): array
    {
        return $this->query->remember($filters, 'technology', function () use ($filters): array {
            $breakdowns = [];

            foreach (self::BREAKDOWNS as $dimension) {
                $breakdowns[$dimension] = $this->query->breakdown($filters, $dimension, 10);
            }

            return [
                'breakdowns' => $breakdowns,
                'themes' => $this->themeMatrix($filters),
                'cpu' => $this->numericBuckets($filters, 'cpu_cores', [[2, '≤ 2 cores'], [4, '3–4 cores'], [8, '5–8 cores'], [PHP_INT_MAX, '9+ cores']]),
                // navigator.deviceMemory is capped at 8 by browsers.
                'memory' => $this->numericBuckets($filters, 'memory_gb', [[2, '≤ 2 GB'], [4, '4 GB'], [PHP_INT_MAX, '8 GB+']]),
                'pixelRatio' => $this->numericBuckets($filters, 'pixel_ratio', [[1, '1x'], [1.5, '1.25–1.5x'], [2, '2x'], [PHP_INT_MAX, 'Above 2x']]),
                'bots' => $this->bots($filters),
            ];
        });
    }

    /**
     * OS preference vs the theme actually used on the site — how many people
     * flip the website's theme away from what their OS asks for.
     *
     * @return list<array{os: string, site: string, sessions: int}>
     */
    private function themeMatrix(WebAnalyticsFilters $filters): array
    {
        return $this->query->sessions($filters)
            ->toBase()
            ->selectRaw("COALESCE(color_scheme, '".WebAnalyticsQuery::NONE."') as os_scheme, COALESCE(site_theme, '".WebAnalyticsQuery::NONE."') as site, COUNT(*) as sessions")
            ->groupByRaw("COALESCE(color_scheme, '".WebAnalyticsQuery::NONE."'), COALESCE(site_theme, '".WebAnalyticsQuery::NONE."')")
            ->orderByDesc('sessions')
            ->get()
            ->map(fn (object $row): array => ['os' => $row->os_scheme, 'site' => $row->site, 'sessions' => (int) $row->sessions])
            ->all();
    }

    /**
     * A list of pairs rather than a bound => label map: PHP would truncate a
     * float key like 1.5 to 1.
     *
     * @param  list<array{0: int|float, 1: string}>  $bounds  [inclusive upper bound, label], ascending
     * @return list<array{label: string, count: int}>
     */
    private function numericBuckets(WebAnalyticsFilters $filters, string $column, array $bounds): array
    {
        $buckets = array_fill_keys(array_column($bounds, 1), 0);

        $this->query->sessions($filters)
            ->toBase()
            ->whereNotNull($column)
            ->selectRaw("{$column} as value, COUNT(*) as total")
            ->groupBy($column)
            ->get()
            ->each(function (object $row) use (&$buckets, $bounds): void {
                foreach ($bounds as [$bound, $label]) {
                    if ((float) $row->value <= (float) $bound) {
                        $buckets[$label] += (int) $row->total;

                        return;
                    }
                }
            });

        return array_map(fn (string $label, int $count): array => ['label' => $label, 'count' => $count], array_keys($buckets), $buckets);
    }

    /**
     * @return array{total: int, top: list<array{name: string, category: ?string, hits: int}>, daily: array{labels: list<string>, values: list<int>}}
     */
    private function bots(WebAnalyticsFilters $filters): array
    {
        $range = [$filters->from->toDateString(), $filters->to->toDateString()];

        $top = WebBotHitSummary::query()
            ->whereBetween('date', $range)
            ->selectRaw('bot_name, MAX(bot_category) as bot_category, SUM(hits) as hits')
            ->groupBy('bot_name')
            ->orderByDesc('hits')
            ->limit(15)
            ->toBase()
            ->get();

        $daily = WebBotHitSummary::query()
            ->whereBetween('date', $range)
            ->selectRaw('date, SUM(hits) as hits')
            ->groupBy('date')
            ->toBase()
            ->pluck('hits', 'date');

        $labels = [];
        $values = [];

        for ($day = $filters->from->copy()->startOfDay(); $day->lte($filters->to); $day->addDay()) {
            $labels[] = $day->format('d M');
            $values[] = (int) ($daily[$day->toDateString()] ?? $daily[$day->toDateString().' 00:00:00'] ?? 0);
        }

        return [
            'total' => (int) $top->sum('hits'),
            'top' => $top->map(fn (object $row): array => ['name' => $row->bot_name, 'category' => $row->bot_category, 'hits' => (int) $row->hits])->all(),
            'daily' => ['labels' => $labels, 'values' => $values],
        ];
    }
}
