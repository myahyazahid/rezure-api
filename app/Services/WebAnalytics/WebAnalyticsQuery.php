<?php

namespace App\Services\WebAnalytics;

use App\Models\WebDailyDimensionSummary;
use App\Models\WebDailyPageSummary;
use App\Models\WebDailySummary;
use App\Models\WebEvent;
use App\Models\WebGoal;
use App\Models\WebPageview;
use App\Models\WebSession;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The query layer every website analytics page is built on: filtered base
 * builders over the raw web_* tables, plus the shared metrics (summary,
 * time series, dimension breakdowns, pages).
 *
 * Source selection follows docs/v4: with no dimension filters and a full
 * set of daily rollups for the past days in range, additive metrics come
 * from the web_*_summary tables and only "today" is read raw. Anything
 * filtered — and unique visitors, which can't be summed across days — is
 * read from the raw tables through their (started_at / occurred_at)
 * indexes.
 */
class WebAnalyticsQuery
{
    /**
     * How a missing dimension value (no referrer, unknown city, ...) is shown,
     * stored in the rollups, and passed back as a drill-down filter.
     */
    public const NONE = '(none)';

    /**
     * @var array<string, bool>
     */
    private array $rollupCoverage = [];

    /**
     * Results for a range that ends before today can't change any more
     * (beyond late hits re-rolled the next night), so they're kept longer.
     */
    public function remember(WebAnalyticsFilters $filters, string $key, Closure $callback): mixed
    {
        $ttl = $filters->includesToday() ? 60 : 3600;

        return Cache::remember($filters->cacheKey($key), $ttl, $callback);
    }

    /**
     * @return Builder<WebSession>
     */
    public function sessions(WebAnalyticsFilters $filters, ?Carbon $fromUtc = null, ?Carbon $toUtc = null): Builder
    {
        $query = WebSession::query()->whereBetween('web_sessions.started_at', [$fromUtc ?? $filters->fromUtc(), $toUtc ?? $filters->toUtc()]);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Joined to web_sessions so session dimensions can filter pageviews; a
     * page filter here narrows to that page's own views.
     *
     * @return Builder<WebPageview>
     */
    public function pageviews(WebAnalyticsFilters $filters, ?Carbon $fromUtc = null, ?Carbon $toUtc = null): Builder
    {
        $query = WebPageview::query()
            ->join('web_sessions', 'web_sessions.id', '=', 'web_pageviews.web_session_id')
            ->whereBetween('web_pageviews.occurred_at', [$fromUtc ?? $filters->fromUtc(), $toUtc ?? $filters->toUtc()]);

        $this->applyFilters($query, $filters, 'web_pageviews.path');

        return $query;
    }

    /**
     * Events in range. Only joined to sessions when there's something to
     * filter by, so session-less downloads still count when unfiltered.
     *
     * @return Builder<WebEvent>
     */
    public function events(WebAnalyticsFilters $filters, ?string $name = null, ?Carbon $fromUtc = null, ?Carbon $toUtc = null): Builder
    {
        $query = WebEvent::query()
            ->whereBetween('web_events.occurred_at', [$fromUtc ?? $filters->fromUtc(), $toUtc ?? $filters->toUtc()])
            ->when($name !== null, fn (Builder $query) => $query->where('web_events.name', $name));

        if ($filters->hasFilters()) {
            $query->join('web_sessions', 'web_sessions.id', '=', 'web_events.web_session_id');
            $this->applyFilters($query, $filters);
        }

        return $query;
    }

    /**
     * Applies the dimension filters to a query that has `web_sessions` (or
     * `$sessionTable`) available. `$pageColumn`, when given, is matched
     * directly by a page filter; otherwise a page filter keeps sessions that
     * viewed the page.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public function applyFilters(Builder|QueryBuilder $query, WebAnalyticsFilters $filters, ?string $pageColumn = null, string $sessionTable = 'web_sessions'): void
    {
        foreach ($filters->dimensions as $key => $value) {
            if (WebDimensions::isSessionDimension($key)) {
                $expression = WebDimensions::expression($key, $sessionTable);

                $value === self::NONE
                    ? $query->whereRaw("{$expression} IS NULL")
                    : $query->whereRaw("{$expression} = ?", [$value]);

                continue;
            }

            if ($key === WebDimensions::PAGE) {
                if ($pageColumn !== null) {
                    $this->wherePathMatches($query, $pageColumn, $value);
                } else {
                    $query->whereExists(fn (QueryBuilder $sub) => $this->wherePathMatches(
                        $sub->selectRaw('1')->from('web_pageviews as filter_pageviews')->whereColumn('filter_pageviews.web_session_id', "{$sessionTable}.id"),
                        'filter_pageviews.path',
                        $value,
                    ));
                }

                continue;
            }

            if ($key === WebDimensions::GOAL && ($goal = WebGoal::find((int) $value)) !== null) {
                $query->whereExists(fn (QueryBuilder $sub) => $this->goalConversions($sub, $goal, "{$sessionTable}.id"));
            }
        }
    }

    /**
     * Narrows `$sub` to rows of a session (`$sessionIdColumn`) that converted
     * on `$goal`.
     */
    public function goalConversions(QueryBuilder $sub, WebGoal $goal, string $sessionIdColumn): QueryBuilder
    {
        if ($goal->type === WebGoal::TYPE_PAGEVIEW) {
            $sub->selectRaw('1')->from('web_pageviews as goal_pageviews')->whereColumn('goal_pageviews.web_session_id', $sessionIdColumn);

            return $this->wherePathMatches($sub, 'goal_pageviews.path', $goal->match);
        }

        return $sub->selectRaw('1')
            ->from('web_events as goal_events')
            ->whereColumn('goal_events.web_session_id', $sessionIdColumn)
            ->where('goal_events.name', $goal->match)
            ->when($goal->label_match, fn (QueryBuilder $query, string $label) => $query->whereRaw(
                "goal_events.label LIKE ? ESCAPE '".WebGoal::LIKE_ESCAPE."'",
                [WebGoal::wildcardToLike($label)],
            ));
    }

    /**
     * Exact match, or a `*` wildcard pattern.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public function wherePathMatches(Builder|QueryBuilder $query, string $column, string $pattern): Builder|QueryBuilder
    {
        if (! str_contains($pattern, '*')) {
            return $query->where($column, $pattern);
        }

        return $query->whereRaw("{$column} LIKE ? ESCAPE '".WebGoal::LIKE_ESCAPE."'", [WebGoal::wildcardToLike($pattern)]);
    }

    public function visitorCount(WebAnalyticsFilters $filters, ?Carbon $fromUtc = null, ?Carbon $toUtc = null): int
    {
        return (int) $this->sessions($filters, $fromUtc, $toUtc)->toBase()->selectRaw('COUNT(DISTINCT web_visitor_id) as visitors')->value('visitors');
    }

    /**
     * Headline numbers for the period.
     *
     * @return array{visitors: int, sessions: int, pageviews: int, bounces: int, engaged_sessions: int, total_duration: int, total_engaged: int, downloads: int, conversions: int, views_per_session: float, bounce_rate: float, avg_duration: float, avg_engaged: float, conversion_rate: float}
     */
    public function summary(WebAnalyticsFilters $filters): array
    {
        return $this->derive([...$this->additiveTotals($filters), 'visitors' => $this->visitorCount($filters)]);
    }

    /**
     * One row per time bucket (hour / day / week / month, see
     * WebAnalyticsFilters::granularity()), zero-filled.
     *
     * @return list<array{key: string, label: string, visitors: int, sessions: int, pageviews: int, bounces: int, total_duration: int, downloads: int, bounce_rate: float, avg_duration: float, views_per_session: float, conversion_rate: float, conversions: int, engaged_sessions: int, total_engaged: int, avg_engaged: float}>
     */
    public function timeseries(WebAnalyticsFilters $filters): array
    {
        $granularity = $filters->granularity();
        $buckets = $this->bucketLabels($filters, $granularity);
        $totals = array_fill_keys(array_keys($buckets), $this->emptyTotals());

        $rawFrom = $filters->fromUtc();

        if ($granularity !== 'hour' && $this->canUseRollups($filters)) {
            [$pastFrom, $pastTo] = $this->pastDates($filters);

            WebDailySummary::query()
                ->whereBetween('date', [$pastFrom, $pastTo])
                ->get()
                ->each(function (WebDailySummary $row) use (&$totals, $granularity): void {
                    $key = $this->bucketKeyForDate(Carbon::parse($row->date->toDateString(), config('app.display_timezone')), $granularity);

                    if (isset($totals[$key])) {
                        $totals[$key] = $this->addTotals($totals[$key], $this->summaryRowTotals($row));
                    }
                });

            $rawFrom = $filters->includesToday() ? now(config('app.display_timezone'))->startOfDay()->utc() : null;
        }

        if ($rawFrom !== null) {
            foreach ($this->rawTotalsByBucket($filters, $granularity, $rawFrom, $filters->toUtc()) as $key => $row) {
                if (isset($totals[$key])) {
                    $totals[$key] = $this->addTotals($totals[$key], $row);
                }
            }
        }

        $bucketExpression = LocalTime::bucket('web_sessions.started_at', $granularity);
        $visitors = $this->sessions($filters)
            ->toBase()
            ->selectRaw("{$bucketExpression} as bucket, COUNT(DISTINCT web_visitor_id) as visitors")
            ->groupByRaw($bucketExpression)
            ->pluck('visitors', 'bucket');

        $series = [];

        foreach ($buckets as $key => $label) {
            $series[] = ['key' => $key, 'label' => $label, ...$this->derive([...$totals[$key], 'visitors' => (int) ($visitors[$key] ?? 0)])];
        }

        return $series;
    }

    /**
     * Sessions broken down by one session dimension, most sessions first.
     *
     * @return list<array{value: string, visitors: int, sessions: int, pageviews: int, bounces: int, total_duration: int, conversions: int, bounce_rate: float, avg_duration: float, conversion_rate: float, share: float}>
     */
    public function breakdown(WebAnalyticsFilters $filters, string $dimension, int $limit = 10): array
    {
        $expression = WebDimensions::expression($dimension);

        if ($this->canUseRollups($filters)) {
            [$pastFrom, $pastTo] = $this->pastDates($filters);

            $rows = WebDailyDimensionSummary::query()
                ->where('dimension', $dimension)
                ->whereBetween('date', [$pastFrom, $pastTo])
                ->groupBy('value')
                ->selectRaw('value, SUM(sessions) as sessions, SUM(pageviews) as pageviews, SUM(bounces) as bounces, SUM(total_duration_seconds) as total_duration, SUM(conversions) as conversions')
                ->toBase()
                ->get()
                ->mapWithKeys(fn (object $row): array => [(string) $row->value => $this->breakdownTotals($row)])
                ->all();

            if ($filters->includesToday()) {
                $today = now(config('app.display_timezone'))->startOfDay()->utc();

                foreach ($this->rawBreakdownTotals($filters, $expression, $today, $filters->toUtc()) as $value => $row) {
                    $rows[$value] = isset($rows[$value]) ? $this->addTotals($rows[$value], $row) : $row;
                }
            }
        } else {
            $rows = $this->rawBreakdownTotals($filters, $expression, $filters->fromUtc(), $filters->toUtc());
        }

        $totalSessions = max(1, array_sum(array_column($rows, 'sessions')));

        uasort($rows, fn (array $a, array $b): int => [$b['sessions'], $b['pageviews']] <=> [$a['sessions'], $a['pageviews']]);
        $rows = array_slice($rows, 0, $limit, true);

        $visitors = $this->visitorsByValue($filters, $expression, array_keys($rows));

        $result = [];

        foreach ($rows as $value => $row) {
            $value = (string) $value;
            $result[] = [
                'value' => $value,
                ...$this->derive([...$this->emptyTotals(), ...$row, 'visitors' => $visitors[$value] ?? 0]),
                'share' => round($row['sessions'] / $totalSessions * 100, 1),
            ];
        }

        return $result;
    }

    /**
     * Per-page metrics. `$sort` is `pageviews`, `entries` (landing pages)
     * or `exits`.
     *
     * @return list<array{path: string, pageviews: int, visitors: int, entries: int, exits: int, bounces: int, avg_engaged_seconds: float, avg_scroll: float, exit_rate: float, bounce_rate: float, not_found: int}>
     */
    public function pages(WebAnalyticsFilters $filters, string $sort = 'pageviews', int $limit = 50, ?string $search = null): array
    {
        if ($this->canUseRollups($filters)) {
            [$pastFrom, $pastTo] = $this->pastDates($filters);

            $rows = WebDailyPageSummary::query()
                ->whereBetween('date', [$pastFrom, $pastTo])
                ->when($search, fn (Builder $query, string $search) => $query->where('path', 'like', '%'.$search.'%'))
                ->groupBy('path')
                ->selectRaw('path, SUM(pageviews) as pageviews, SUM(entries) as entries, SUM(exits) as exits, SUM(bounces) as bounces, SUM(total_engaged_ms) as engaged_ms, SUM(total_scroll_pct) as scroll, SUM(not_found) as not_found')
                ->toBase()
                ->get()
                ->mapWithKeys(fn (object $row): array => [$row->path => $this->pageTotals($row)])
                ->all();

            if ($filters->includesToday()) {
                $today = now(config('app.display_timezone'))->startOfDay()->utc();

                foreach ($this->rawPageTotals($filters, $today, $filters->toUtc(), $search) as $path => $row) {
                    $rows[$path] = isset($rows[$path]) ? $this->addTotals($rows[$path], $row) : $row;
                }
            }
        } else {
            $rows = $this->rawPageTotals($filters, $filters->fromUtc(), $filters->toUtc(), $search);
        }

        $sortKey = in_array($sort, ['entries', 'exits'], true) ? $sort : 'pageviews';
        $rows = array_filter($rows, fn (array $row): bool => $row[$sortKey] > 0);
        uasort($rows, fn (array $a, array $b): int => [$b[$sortKey], $b['pageviews']] <=> [$a[$sortKey], $a['pageviews']]);
        $rows = array_slice($rows, 0, $limit, true);

        $visitors = $rows === [] ? collect() : $this->pageviews($filters)
            ->toBase()
            ->whereIn('web_pageviews.path', array_map('strval', array_keys($rows)))
            ->selectRaw('web_pageviews.path as path, COUNT(DISTINCT web_pageviews.web_visitor_id) as visitors')
            ->groupBy('web_pageviews.path')
            ->pluck('visitors', 'path');

        $result = [];

        foreach ($rows as $path => $row) {
            $result[] = [
                'path' => (string) $path,
                'pageviews' => $row['pageviews'],
                'visitors' => (int) ($visitors[$path] ?? 0),
                'entries' => $row['entries'],
                'exits' => $row['exits'],
                'bounces' => $row['bounces'],
                'avg_engaged_seconds' => $row['pageviews'] > 0 ? round($row['engaged_ms'] / $row['pageviews'] / 1000, 1) : 0.0,
                'avg_scroll' => $row['pageviews'] > 0 ? round($row['scroll'] / $row['pageviews'], 1) : 0.0,
                'exit_rate' => $row['pageviews'] > 0 ? round($row['exits'] / $row['pageviews'] * 100, 1) : 0.0,
                'bounce_rate' => $row['entries'] > 0 ? round($row['bounces'] / $row['entries'] * 100, 1) : 0.0,
                'not_found' => $row['not_found'],
            ];
        }

        return $result;
    }

    /**
     * Ordered bucket key => display label for the whole range.
     *
     * @return array<string, string>
     */
    public function bucketLabels(WebAnalyticsFilters $filters, string $granularity): array
    {
        $cursor = $filters->from->copy();
        $end = $filters->to->copy();
        $labels = [];

        $cursor = match ($granularity) {
            'hour' => $cursor->startOfHour(),
            'week' => $cursor->startOfWeek(Carbon::MONDAY),
            'month' => $cursor->startOfMonth(),
            default => $cursor->startOfDay(),
        };

        while ($cursor->lte($end)) {
            $labels[$this->bucketKeyForDate($cursor, $granularity)] = match ($granularity) {
                'hour' => $filters->days() > 1 ? $cursor->format('d M H:00') : $cursor->format('H:00'),
                'month' => $cursor->format('M Y'),
                default => $cursor->format('d M'),
            };

            match ($granularity) {
                'hour' => $cursor->addHour(),
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonthNoOverflow(),
                default => $cursor->addDay(),
            };
        }

        return $labels;
    }

    /**
     * Whether the daily rollups can stand in for raw rows: no dimension
     * filters, at least one finished day in range, and a summary row for
     * every one of those days (the nightly command writes one even for a
     * zero-traffic day, so a gap means it hasn't run, not "no visits").
     */
    public function canUseRollups(WebAnalyticsFilters $filters): bool
    {
        if ($filters->hasFilters()) {
            return false;
        }

        $past = $this->pastDates($filters);

        if ($past === null) {
            return false;
        }

        $key = implode('|', $past);

        return $this->rollupCoverage[$key] ??= WebDailySummary::query()->whereBetween('date', $past)->count()
            === (int) Carbon::parse($past[0])->diffInDays(Carbon::parse($past[1])) + 1;
    }

    /**
     * The finished (before today) days of the range as Y-m-d strings, or
     * null when the range is only today.
     *
     * @return array{0: string, 1: string}|null
     */
    private function pastDates(WebAnalyticsFilters $filters): ?array
    {
        $yesterday = now(config('app.display_timezone'))->subDay()->startOfDay();
        $from = $filters->from->copy()->startOfDay();
        $to = $filters->to->copy()->startOfDay()->min($yesterday);

        return $from->lte($to) ? [$from->toDateString(), $to->toDateString()] : null;
    }

    /**
     * @return array{sessions: int, pageviews: int, bounces: int, engaged_sessions: int, total_duration: int, total_engaged: int, downloads: int, conversions: int}
     */
    private function additiveTotals(WebAnalyticsFilters $filters): array
    {
        if (! $this->canUseRollups($filters)) {
            return $this->rawTotals($filters, $filters->fromUtc(), $filters->toUtc());
        }

        $totals = $this->emptyTotals();

        WebDailySummary::query()
            ->whereBetween('date', $this->pastDates($filters))
            ->get()
            ->each(function (WebDailySummary $row) use (&$totals): void {
                $totals = $this->addTotals($totals, $this->summaryRowTotals($row));
            });

        if ($filters->includesToday()) {
            $totals = $this->addTotals($totals, $this->rawTotals($filters, now(config('app.display_timezone'))->startOfDay()->utc(), $filters->toUtc()));
        }

        return $totals;
    }

    /**
     * @return array{sessions: int, pageviews: int, bounces: int, engaged_sessions: int, total_duration: int, total_engaged: int, downloads: int, conversions: int}
     */
    private function rawTotals(WebAnalyticsFilters $filters, Carbon $fromUtc, Carbon $toUtc): array
    {
        $sessions = $this->sessions($filters, $fromUtc, $toUtc)
            ->toBase()
            ->selectRaw('COUNT(*) as sessions, SUM(is_bounce) as bounces, SUM(is_engaged) as engaged_sessions, SUM(duration_seconds) as total_duration, SUM(engaged_seconds) as total_engaged, SUM(has_conversion) as conversions')
            ->first();

        return [
            'sessions' => (int) $sessions->sessions,
            'pageviews' => $this->pageviews($filters, $fromUtc, $toUtc)->count(),
            'bounces' => (int) $sessions->bounces,
            'engaged_sessions' => (int) $sessions->engaged_sessions,
            'total_duration' => (int) $sessions->total_duration,
            'total_engaged' => (int) $sessions->total_engaged,
            'downloads' => $this->events($filters, WebEvent::DOWNLOAD, $fromUtc, $toUtc)->count(),
            'conversions' => (int) $sessions->conversions,
        ];
    }

    /**
     * @return array<string, array{sessions: int, pageviews: int, bounces: int, engaged_sessions: int, total_duration: int, total_engaged: int, downloads: int, conversions: int}>
     */
    private function rawTotalsByBucket(WebAnalyticsFilters $filters, string $granularity, Carbon $fromUtc, Carbon $toUtc): array
    {
        $sessionBucket = LocalTime::bucket('web_sessions.started_at', $granularity);
        $pageviewBucket = LocalTime::bucket('web_pageviews.occurred_at', $granularity);
        $eventBucket = LocalTime::bucket('web_events.occurred_at', $granularity);

        $rows = [];

        $this->sessions($filters, $fromUtc, $toUtc)
            ->toBase()
            ->selectRaw("{$sessionBucket} as bucket, COUNT(*) as sessions, SUM(is_bounce) as bounces, SUM(is_engaged) as engaged_sessions, SUM(duration_seconds) as total_duration, SUM(engaged_seconds) as total_engaged, SUM(has_conversion) as conversions")
            ->groupByRaw($sessionBucket)
            ->get()
            ->each(function (object $row) use (&$rows): void {
                $rows[$row->bucket] = [
                    ...$this->emptyTotals(),
                    'sessions' => (int) $row->sessions,
                    'bounces' => (int) $row->bounces,
                    'engaged_sessions' => (int) $row->engaged_sessions,
                    'total_duration' => (int) $row->total_duration,
                    'total_engaged' => (int) $row->total_engaged,
                    'conversions' => (int) $row->conversions,
                ];
            });

        $this->pageviews($filters, $fromUtc, $toUtc)
            ->toBase()
            ->selectRaw("{$pageviewBucket} as bucket, COUNT(*) as pageviews")
            ->groupByRaw($pageviewBucket)
            ->get()
            ->each(function (object $row) use (&$rows): void {
                $rows[$row->bucket] = [...($rows[$row->bucket] ?? $this->emptyTotals()), 'pageviews' => (int) $row->pageviews];
            });

        $this->events($filters, WebEvent::DOWNLOAD, $fromUtc, $toUtc)
            ->toBase()
            ->selectRaw("{$eventBucket} as bucket, COUNT(*) as downloads")
            ->groupByRaw($eventBucket)
            ->get()
            ->each(function (object $row) use (&$rows): void {
                $rows[$row->bucket] = [...($rows[$row->bucket] ?? $this->emptyTotals()), 'downloads' => (int) $row->downloads];
            });

        return $rows;
    }

    /**
     * @return array<string, array{sessions: int, pageviews: int, bounces: int, total_duration: int, conversions: int}>
     */
    private function rawBreakdownTotals(WebAnalyticsFilters $filters, string $expression, Carbon $fromUtc, Carbon $toUtc): array
    {
        $value = "COALESCE({$expression}, '".self::NONE."')";

        return $this->sessions($filters, $fromUtc, $toUtc)
            ->toBase()
            ->selectRaw("{$value} as value, COUNT(*) as sessions, SUM(pageviews_count) as pageviews, SUM(is_bounce) as bounces, SUM(duration_seconds) as total_duration, SUM(has_conversion) as conversions")
            ->groupByRaw($value)
            ->get()
            ->mapWithKeys(fn (object $row): array => [(string) $row->value => $this->breakdownTotals($row)])
            ->all();
    }

    /**
     * @param  list<int|string>  $values
     * @return array<string, int>
     */
    private function visitorsByValue(WebAnalyticsFilters $filters, string $expression, array $values): array
    {
        if ($values === []) {
            return [];
        }

        $value = "COALESCE({$expression}, '".self::NONE."')";
        $values = array_map('strval', $values);

        return $this->sessions($filters)
            ->toBase()
            ->whereRaw("{$value} IN (".implode(', ', array_fill(0, count($values), '?')).')', $values)
            ->selectRaw("{$value} as value, COUNT(DISTINCT web_visitor_id) as visitors")
            ->groupByRaw($value)
            ->pluck('visitors', 'value')
            ->map(fn (mixed $visitors): int => (int) $visitors)
            ->all();
    }

    /**
     * @return array<string, array{pageviews: int, entries: int, exits: int, bounces: int, engaged_ms: int, scroll: int, not_found: int}>
     */
    private function rawPageTotals(WebAnalyticsFilters $filters, Carbon $fromUtc, Carbon $toUtc, ?string $search): array
    {
        return $this->pageviews($filters, $fromUtc, $toUtc)
            ->toBase()
            ->when($search, fn (QueryBuilder $query, string $search) => $query->where('web_pageviews.path', 'like', '%'.$search.'%'))
            ->selectRaw('web_pageviews.path as path, COUNT(*) as pageviews, SUM(web_pageviews.is_entry) as entries, SUM(web_pageviews.is_exit) as exits, SUM(CASE WHEN web_pageviews.is_entry = 1 AND web_sessions.is_bounce = 1 THEN 1 ELSE 0 END) as bounces, SUM(web_pageviews.engaged_ms) as engaged_ms, SUM(web_pageviews.max_scroll_pct) as scroll, SUM(web_pageviews.is_not_found) as not_found')
            ->groupBy('web_pageviews.path')
            ->get()
            ->mapWithKeys(fn (object $row): array => [$row->path => $this->pageTotals($row)])
            ->all();
    }

    /**
     * @return array{pageviews: int, entries: int, exits: int, bounces: int, engaged_ms: int, scroll: int, not_found: int}
     */
    private function pageTotals(object $row): array
    {
        return [
            'pageviews' => (int) $row->pageviews,
            'entries' => (int) $row->entries,
            'exits' => (int) $row->exits,
            'bounces' => (int) $row->bounces,
            'engaged_ms' => (int) $row->engaged_ms,
            'scroll' => (int) $row->scroll,
            'not_found' => (int) $row->not_found,
        ];
    }

    /**
     * @return array{sessions: int, pageviews: int, bounces: int, total_duration: int, conversions: int}
     */
    private function breakdownTotals(object $row): array
    {
        return [
            'sessions' => (int) $row->sessions,
            'pageviews' => (int) $row->pageviews,
            'bounces' => (int) $row->bounces,
            'total_duration' => (int) $row->total_duration,
            'conversions' => (int) $row->conversions,
        ];
    }

    /**
     * @return array{sessions: int, pageviews: int, bounces: int, engaged_sessions: int, total_duration: int, total_engaged: int, downloads: int, conversions: int}
     */
    private function summaryRowTotals(WebDailySummary $row): array
    {
        return [
            'sessions' => $row->sessions,
            'pageviews' => $row->pageviews,
            'bounces' => $row->bounces,
            'engaged_sessions' => $row->engaged_sessions,
            'total_duration' => $row->total_duration_seconds,
            'total_engaged' => $row->total_engaged_seconds,
            'downloads' => $row->downloads,
            'conversions' => $row->conversions,
        ];
    }

    /**
     * @return array{sessions: int, pageviews: int, bounces: int, engaged_sessions: int, total_duration: int, total_engaged: int, downloads: int, conversions: int}
     */
    private function emptyTotals(): array
    {
        return [
            'sessions' => 0,
            'pageviews' => 0,
            'bounces' => 0,
            'engaged_sessions' => 0,
            'total_duration' => 0,
            'total_engaged' => 0,
            'downloads' => 0,
            'conversions' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $a
     * @param  array<string, int>  $b
     * @return array<string, int>
     */
    private function addTotals(array $a, array $b): array
    {
        foreach ($b as $key => $value) {
            $a[$key] = ($a[$key] ?? 0) + $value;
        }

        return $a;
    }

    /**
     * Ratios computed from totals — never averaged across rows.
     *
     * @param  array<string, int>  $totals
     * @return array<string, int|float>
     */
    public function derive(array $totals): array
    {
        $sessions = $totals['sessions'] ?? 0;

        return [
            ...$totals,
            'views_per_session' => $sessions > 0 ? round(($totals['pageviews'] ?? 0) / $sessions, 2) : 0.0,
            'bounce_rate' => $sessions > 0 ? round(($totals['bounces'] ?? 0) / $sessions * 100, 1) : 0.0,
            'avg_duration' => $sessions > 0 ? round(($totals['total_duration'] ?? 0) / $sessions, 1) : 0.0,
            'avg_engaged' => $sessions > 0 ? round(($totals['total_engaged'] ?? 0) / $sessions, 1) : 0.0,
            'conversion_rate' => $sessions > 0 ? round(($totals['conversions'] ?? 0) / $sessions * 100, 2) : 0.0,
        ];
    }

    private function bucketKeyForDate(Carbon $date, string $granularity): string
    {
        return match ($granularity) {
            'hour' => $date->format('Y-m-d H:00'),
            'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            'month' => $date->format('Y-m-01'),
            default => $date->format('Y-m-d'),
        };
    }

    /**
     * Percentage change from `$previous` to `$current`, or null when there
     * is nothing to compare against.
     */
    public static function change(int|float $current, int|float $previous): ?float
    {
        if ($previous == 0) {
            return $current == 0 ? 0.0 : null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * Values for the period plus, when comparison is on, the same metrics
     * for the comparison period and the change between them.
     *
     * @param  Closure(WebAnalyticsFilters): array<string, int|float>  $metrics
     * @return array{current: array<string, int|float>, previous: ?array<string, int|float>, change: array<string, ?float>}
     */
    public function compared(WebAnalyticsFilters $filters, Closure $metrics): array
    {
        $current = $metrics($filters);
        $comparison = $filters->comparison();
        $previous = $comparison !== null ? $metrics($comparison) : null;

        $change = [];

        foreach ($current as $key => $value) {
            $change[$key] = $previous !== null && is_numeric($value) ? self::change($value, $previous[$key] ?? 0) : null;
        }

        return ['current' => $current, 'previous' => $previous, 'change' => $change];
    }

    /**
     * @param  Collection<int, mixed>|array<int, mixed>  $values
     */
    public static function percentile(Collection|array $values, float $percentile): ?float
    {
        $sorted = collect($values)->filter(fn (mixed $value): bool => $value !== null)->map(fn (mixed $value): float => (float) $value)->sort()->values();

        if ($sorted->isEmpty()) {
            return null;
        }

        $index = (int) ceil($percentile / 100 * $sorted->count()) - 1;

        return $sorted[max(0, min($index, $sorted->count() - 1))];
    }
}
