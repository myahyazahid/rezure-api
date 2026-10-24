<?php

namespace App\Services\WebAnalytics\Reports;

use App\Models\WebError;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * Website › Performance (Core Web Vitals from real visitors) and
 * Website › Errors (uncaught JavaScript errors).
 */
class PerformanceReport
{
    /**
     * Google's thresholds: at or under the first is "good", over the second
     * is "poor", in between "needs improvement". Measured at p75.
     *
     * @var array<string, array{label: string, good: float, poor: float, unit: string}>
     */
    public const VITALS = [
        'lcp_ms' => ['label' => 'LCP', 'good' => 2500, 'poor' => 4000, 'unit' => 'ms'],
        'inp_ms' => ['label' => 'INP', 'good' => 200, 'poor' => 500, 'unit' => 'ms'],
        'cls' => ['label' => 'CLS', 'good' => 0.1, 'poor' => 0.25, 'unit' => ''],
        'fcp_ms' => ['label' => 'FCP', 'good' => 1800, 'poor' => 3000, 'unit' => 'ms'],
        'ttfb_ms' => ['label' => 'TTFB', 'good' => 800, 'poor' => 1800, 'unit' => 'ms'],
    ];

    /**
     * Vitals are percentiles, computed in PHP over (at most) this many of
     * the latest measured pageviews.
     */
    private const SAMPLE = 20000;

    public function __construct(private readonly WebAnalyticsQuery $query) {}

    /**
     * @return array<string, mixed>
     */
    public function build(WebAnalyticsFilters $filters): array
    {
        return $this->query->remember($filters, 'performance', function () use ($filters): array {
            $samples = $this->query->pageviews($filters)
                ->where(fn ($query) => $query->whereNotNull('web_pageviews.lcp_ms')->orWhereNotNull('web_pageviews.ttfb_ms')->orWhereNotNull('web_pageviews.page_load_ms'))
                ->latest('web_pageviews.occurred_at')
                ->limit(self::SAMPLE)
                ->get(['web_pageviews.path', 'web_pageviews.occurred_at', 'web_sessions.device_type', 'web_pageviews.lcp_ms', 'web_pageviews.inp_ms', 'web_pageviews.cls', 'web_pageviews.fcp_ms', 'web_pageviews.ttfb_ms', 'web_pageviews.page_load_ms']);

            return [
                'samples' => $samples->count(),
                'overall' => $this->vitals($samples),
                'byDevice' => $samples->groupBy(fn ($row): string => $row->device_type ?? 'unknown')
                    ->map(fn (Collection $rows): array => ['samples' => $rows->count(), 'vitals' => $this->vitals($rows)])
                    ->sortByDesc('samples')
                    ->all(),
                'byPage' => $samples->groupBy('path')
                    ->map(fn (Collection $rows, string $path): array => [
                        'path' => $path,
                        'samples' => $rows->count(),
                        'vitals' => $this->vitals($rows),
                        'page_load' => WebAnalyticsQuery::percentile($rows->pluck('page_load_ms'), 75),
                    ])
                    ->sortByDesc('samples')
                    ->take(25)
                    ->values()
                    ->all(),
                'slowest' => $samples->groupBy('path')
                    ->filter(fn (Collection $rows): bool => $rows->whereNotNull('page_load_ms')->count() >= 3)
                    ->map(fn (Collection $rows, string $path): array => ['path' => $path, 'page_load' => WebAnalyticsQuery::percentile($rows->pluck('page_load_ms'), 75), 'samples' => $rows->count()])
                    ->sortByDesc('page_load')
                    ->take(10)
                    ->values()
                    ->all(),
                'trend' => $this->trend($samples),
            ];
        });
    }

    /**
     * Errors grouped by fingerprint, most frequent first.
     *
     * @return list<array{fingerprint: string, message: string, source: ?string, line: ?int, occurrences: int, sessions: int, first_seen: string, last_seen: string, top_path: ?string}>
     */
    public function errors(WebAnalyticsFilters $filters): array
    {
        return $this->errorsQuery($filters)
            ->selectRaw('web_errors.fingerprint as fingerprint, MAX(web_errors.message) as message, MAX(web_errors.source) as source, MAX(web_errors.line) as line, COUNT(*) as occurrences, COUNT(DISTINCT web_errors.web_session_id) as sessions, MIN(web_errors.occurred_at) as first_seen, MAX(web_errors.occurred_at) as last_seen, MAX(web_errors.path) as top_path')
            ->groupBy('web_errors.fingerprint')
            ->orderByDesc('occurrences')
            ->limit(50)
            ->get()
            ->map(fn (object $row): array => [
                'fingerprint' => $row->fingerprint,
                'message' => $row->message,
                'source' => $row->source,
                'line' => $row->line !== null ? (int) $row->line : null,
                'occurrences' => (int) $row->occurrences,
                'sessions' => (int) $row->sessions,
                'first_seen' => $row->first_seen,
                'last_seen' => $row->last_seen,
                'top_path' => $row->top_path,
            ])
            ->all();
    }

    /**
     * One error group: a sample with its stack, and where it happens.
     *
     * @return array{sample: ?WebError, occurrences: int, browsers: list<array{label: string, total: int}>, pages: list<array{label: string, total: int}>, recent: Collection<int, WebError>}
     */
    public function errorDetail(WebAnalyticsFilters $filters, string $fingerprint): array
    {
        $group = fn (string $column): array => $this->errorsQuery($filters)
            ->where('web_errors.fingerprint', $fingerprint)
            ->selectRaw("COALESCE({$column}, '".WebAnalyticsQuery::NONE."') as label, COUNT(*) as total")
            ->groupByRaw("COALESCE({$column}, '".WebAnalyticsQuery::NONE."')")
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => ['label' => (string) $row->label, 'total' => (int) $row->total])
            ->all();

        return [
            'sample' => WebError::query()->where('fingerprint', $fingerprint)->latest('occurred_at')->first(),
            'occurrences' => $this->errorsQuery($filters)->where('web_errors.fingerprint', $fingerprint)->count(),
            'browsers' => $group('web_errors.browser'),
            'pages' => $group('web_errors.path'),
            'recent' => WebError::query()->with('session:id,session_uuid,country_code,city,os,device_type')->where('fingerprint', $fingerprint)->latest('occurred_at')->limit(25)->get(),
        ];
    }

    /**
     * p75 of every vital over a set of measured pageviews, with its rating.
     *
     * @param  Collection<int, object>  $rows
     * @return array<string, array{label: string, p75: ?float, rating: ?string, unit: string, distribution: array{good: float, needs: float, poor: float}}>
     */
    private function vitals(Collection $rows): array
    {
        $result = [];

        foreach (self::VITALS as $field => $meta) {
            $values = $rows->pluck($field)->filter(fn (mixed $value): bool => $value !== null)->map(fn (mixed $value): float => (float) $value);
            $p75 = WebAnalyticsQuery::percentile($values, 75);
            $total = max(1, $values->count());

            $result[$field] = [
                'label' => $meta['label'],
                'p75' => $p75,
                'unit' => $meta['unit'],
                'rating' => $p75 === null ? null : $this->rating($field, $p75),
                'samples' => $values->count(),
                'distribution' => [
                    'good' => round($values->filter(fn (float $value): bool => $value <= $meta['good'])->count() / $total * 100, 1),
                    'needs' => round($values->filter(fn (float $value): bool => $value > $meta['good'] && $value <= $meta['poor'])->count() / $total * 100, 1),
                    'poor' => round($values->filter(fn (float $value): bool => $value > $meta['poor'])->count() / $total * 100, 1),
                ],
            ];
        }

        return $result;
    }

    public function rating(string $field, float $value): string
    {
        $meta = self::VITALS[$field];

        return match (true) {
            $value <= $meta['good'] => 'good',
            $value <= $meta['poor'] => 'needs',
            default => 'poor',
        };
    }

    /**
     * Daily p75 LCP and INP.
     *
     * @param  Collection<int, object>  $rows
     * @return array{labels: list<string>, series: list<array{name: string, data: list<?float>}>}
     */
    private function trend(Collection $rows): array
    {
        $timezone = config('app.display_timezone');
        $byDay = $rows->groupBy(fn ($row): string => $row->occurred_at->copy()->setTimezone($timezone)->toDateString())->sortKeys();

        return [
            'labels' => $byDay->keys()->map(fn (string $day): string => date('d M', strtotime($day)))->values()->all(),
            'series' => [
                ['name' => 'LCP p75 (ms)', 'data' => $byDay->map(fn (Collection $day): ?float => WebAnalyticsQuery::percentile($day->pluck('lcp_ms'), 75))->values()->all()],
                ['name' => 'INP p75 (ms)', 'data' => $byDay->map(fn (Collection $day): ?float => WebAnalyticsQuery::percentile($day->pluck('inp_ms'), 75))->values()->all()],
            ],
        ];
    }

    private function errorsQuery(WebAnalyticsFilters $filters): QueryBuilder
    {
        $query = WebError::query()
            ->join('web_sessions', 'web_sessions.id', '=', 'web_errors.web_session_id')
            ->whereBetween('web_errors.occurred_at', [$filters->fromUtc(), $filters->toUtc()]);

        $this->query->applyFilters($query, $filters, 'web_errors.path');

        return $query->toBase();
    }
}
