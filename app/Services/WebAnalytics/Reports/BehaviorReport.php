<?php

namespace App\Services\WebAnalytics\Reports;

use App\Models\WebClick;
use App\Models\WebPageview;
use App\Services\WebAnalytics\LocalTime;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use App\Services\WebAnalytics\WebDimensions;
use Illuminate\Database\Query\Builder;

/**
 * Website › Behavior and the click heatmap: how visitors move through and
 * interact with the site.
 */
class BehaviorReport
{
    /**
     * Steps of the user flow diagram, and how many sessions it samples —
     * the flow is built in PHP from (session, step, path) rows.
     */
    private const FLOW_STEPS = 3;

    private const FLOW_SESSION_SAMPLE = 5000;

    private const FLOW_MAX_LINKS = 30;

    /**
     * Representative viewport width per bucket — the width the heatmap's
     * iframe renders the page at.
     *
     * @var array<string, int>
     */
    public const HEATMAP_WIDTHS = ['mobile' => 390, 'tablet' => 820, 'desktop' => 1280, 'wide' => 1536];

    public function __construct(private readonly WebAnalyticsQuery $query) {}

    /**
     * @return array<string, mixed>
     */
    public function build(WebAnalyticsFilters $filters): array
    {
        return $this->query->remember($filters, 'behavior', function () use ($filters): array {
            return [
                'engagedTime' => $this->histogram($filters, 'engaged_seconds', [10 => '< 10s', 30 => '10–30s', 60 => '30s–1m', 180 => '1–3m', 600 => '3–10m', PHP_INT_MAX => '10m+']),
                'duration' => $this->histogram($filters, 'duration_seconds', [10 => '< 10s', 30 => '10–30s', 60 => '30s–1m', 180 => '1–3m', 600 => '3–10m', PHP_INT_MAX => '10m+']),
                'depth' => $this->histogram($filters, 'pageviews_count', [1 => '1 page', 2 => '2', 3 => '3', 4 => '4', 9 => '5–9', PHP_INT_MAX => '10+']),
                'scroll' => $this->scrollHistogram($filters),
                'flow' => $this->flow($filters),
                'heatmap' => $this->weekHeatmap($filters),
                'rageClicks' => $this->rageClicks($filters),
            ];
        });
    }

    /**
     * Session column → counts per bucket (bounds are inclusive upper bounds,
     * ascending, the last one catch-all).
     *
     * @param  array<int, string>  $bounds
     * @return list<array{label: string, count: int}>
     */
    private function histogram(WebAnalyticsFilters $filters, string $column, array $bounds): array
    {
        $cases = [];
        $previous = null;

        foreach ($bounds as $bound => $label) {
            $cases[] = $bound === PHP_INT_MAX
                ? "SUM(CASE WHEN {$column} > {$previous} THEN 1 ELSE 0 END)"
                : 'SUM(CASE WHEN '.($previous === null ? '' : "{$column} > {$previous} AND ")."{$column} <= {$bound} THEN 1 ELSE 0 END)";
            $previous = $bound;
        }

        $selects = implode(', ', array_map(fn (string $case, int $index): string => "{$case} as b{$index}", $cases, array_keys($cases)));
        $row = $this->query->sessions($filters)->toBase()->selectRaw($selects)->first();

        return array_map(
            fn (string $label, int $index): array => ['label' => $label, 'count' => (int) ($row->{'b'.$index} ?? 0)],
            array_values($bounds),
            array_keys(array_values($bounds)),
        );
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function scrollHistogram(WebAnalyticsFilters $filters): array
    {
        $row = $this->query->pageviews($filters)
            ->toBase()
            ->selectRaw('SUM(CASE WHEN web_pageviews.max_scroll_pct < 25 THEN 1 ELSE 0 END) as b0, SUM(CASE WHEN web_pageviews.max_scroll_pct >= 25 AND web_pageviews.max_scroll_pct < 50 THEN 1 ELSE 0 END) as b1, SUM(CASE WHEN web_pageviews.max_scroll_pct >= 50 AND web_pageviews.max_scroll_pct < 75 THEN 1 ELSE 0 END) as b2, SUM(CASE WHEN web_pageviews.max_scroll_pct >= 75 AND web_pageviews.max_scroll_pct < 90 THEN 1 ELSE 0 END) as b3, SUM(CASE WHEN web_pageviews.max_scroll_pct >= 90 THEN 1 ELSE 0 END) as b4')
            ->first();

        return array_map(
            fn (string $label, int $index): array => ['label' => $label, 'count' => (int) ($row->{'b'.$index} ?? 0)],
            ['0–25%', '25–50%', '50–75%', '75–90%', '90–100%'],
            range(0, 4),
        );
    }

    /**
     * Entry page → 2nd page → 3rd page, as Sankey links. Node names carry
     * their step ("2 · /download") so the same page at two depths stays two
     * nodes; sessions that stop early flow into "(exit)".
     *
     * @return list<array{from: string, to: string, flow: int}>
     */
    public function flow(WebAnalyticsFilters $filters): array
    {
        $sessionIds = $this->query->sessions($filters)->latest('started_at')->limit(self::FLOW_SESSION_SAMPLE)->pluck('web_sessions.id');

        if ($sessionIds->isEmpty()) {
            return [];
        }

        $paths = [];

        foreach ($sessionIds->chunk(1000) as $chunk) {
            WebPageview::query()
                ->whereIn('web_session_id', $chunk->all())
                ->where('sequence', '<=', self::FLOW_STEPS)
                ->get(['web_session_id', 'sequence', 'path'])
                ->each(function ($pageview) use (&$paths): void {
                    $paths[$pageview->web_session_id][$pageview->sequence] = $pageview->path;
                });
        }

        $links = [];

        foreach ($paths as $steps) {
            ksort($steps);

            for ($step = 1; $step < self::FLOW_STEPS; $step++) {
                if (! isset($steps[$step])) {
                    break;
                }

                $from = $step.' · '.$steps[$step];
                $to = isset($steps[$step + 1]) ? ($step + 1).' · '.$steps[$step + 1] : ($step + 1).' · (exit)';
                $links[$from."\0".$to] = ($links[$from."\0".$to] ?? 0) + 1;

                if (! isset($steps[$step + 1])) {
                    break;
                }
            }
        }

        arsort($links);

        return array_map(function (string $key, int $flow): array {
            [$from, $to] = explode("\0", $key);

            return ['from' => $from, 'to' => $to, 'flow' => $flow];
        }, array_keys(array_slice($links, 0, self::FLOW_MAX_LINKS, true)), array_slice($links, 0, self::FLOW_MAX_LINKS, true));
    }

    /**
     * Sessions per weekday × hour in the display timezone.
     *
     * @return array{rows: list<array{day: string, cells: list<array{hour: int, total: int, intensity: float}>}>, max: int}
     */
    private function weekHeatmap(WebAnalyticsFilters $filters): array
    {
        $day = LocalTime::dayOfWeekIso('web_sessions.started_at');
        $hour = LocalTime::hourOfDay('web_sessions.started_at');

        $counts = [];

        $this->query->sessions($filters)
            ->toBase()
            ->selectRaw("{$day} as weekday, {$hour} as hour, COUNT(*) as total")
            ->groupByRaw("{$day}, {$hour}")
            ->get()
            ->each(function (object $row) use (&$counts): void {
                $counts[(int) $row->weekday][(int) $row->hour] = (int) $row->total;
            });

        $max = max(1, ...array_map(fn (array $hours): int => max($hours), $counts ?: [[0]]));
        $labels = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
        $rows = [];

        foreach ($labels as $iso => $label) {
            $cells = [];

            for ($h = 0; $h < 24; $h++) {
                $total = $counts[$iso][$h] ?? 0;
                $cells[] = ['hour' => $h, 'total' => $total, 'intensity' => round($total / $max, 3)];
            }

            $rows[] = ['day' => $label, 'cells' => $cells];
        }

        return ['rows' => $rows, 'max' => $max];
    }

    /**
     * @return list<array{path: string, selector: ?string, text: ?string, clicks: int, sessions: int}>
     */
    private function rageClicks(WebAnalyticsFilters $filters): array
    {
        return $this->clicks($filters)
            ->where('web_clicks.is_rage', true)
            ->selectRaw('web_clicks.path as path, web_clicks.selector as selector, web_clicks.text as text, COUNT(*) as clicks, COUNT(DISTINCT web_clicks.web_session_id) as sessions')
            ->groupBy('web_clicks.path', 'web_clicks.selector', 'web_clicks.text')
            ->orderByDesc('clicks')
            ->limit(15)
            ->get()
            ->map(fn (object $row): array => [
                'path' => $row->path,
                'selector' => $row->selector,
                'text' => $row->text,
                'clicks' => (int) $row->clicks,
                'sessions' => (int) $row->sessions,
            ])
            ->all();
    }

    /**
     * Click heatmap data for one page at one viewport bucket.
     *
     * @return array{points: list<array{0: float, 1: int, 2: bool}>, height: int, width: int, total: int, elements: list<array{selector: ?string, text: ?string, clicks: int, rage: int}>, pages: list<array{path: string, clicks: int}>}
     */
    public function heatmap(WebAnalyticsFilters $filters, string $path, string $bucket): array
    {
        $clicks = $this->clicks($filters)
            ->where('web_clicks.path', $path)
            ->where('web_clicks.viewport_bucket', $bucket);

        $points = (clone $clicks)
            ->latest('web_clicks.occurred_at')
            ->limit(5000)
            ->get(['web_clicks.x_pct', 'web_clicks.y_px', 'web_clicks.page_height', 'web_clicks.is_rage']);

        $heights = $points->pluck('page_height')->sort()->values();

        return [
            'points' => $points->map(fn (object $click): array => [(float) $click->x_pct, (int) $click->y_px, (bool) $click->is_rage])->all(),
            'height' => (int) ($heights[intdiv($heights->count(), 2)] ?? 2000),
            'width' => self::HEATMAP_WIDTHS[$bucket] ?? 1280,
            'total' => $points->count(),
            'elements' => (clone $clicks)
                ->selectRaw('web_clicks.selector as selector, web_clicks.text as text, COUNT(*) as clicks, SUM(web_clicks.is_rage) as rage')
                ->groupBy('web_clicks.selector', 'web_clicks.text')
                ->orderByDesc('clicks')
                ->limit(15)
                ->get()
                ->map(fn (object $row): array => ['selector' => $row->selector, 'text' => $row->text, 'clicks' => (int) $row->clicks, 'rage' => (int) $row->rage])
                ->all(),
            'pages' => $this->clicks($filters)
                ->selectRaw('web_clicks.path as path, COUNT(*) as clicks')
                ->groupBy('web_clicks.path')
                ->orderByDesc('clicks')
                ->limit(30)
                ->get()
                ->map(fn (object $row): array => ['path' => $row->path, 'clicks' => (int) $row->clicks])
                ->all(),
        ];
    }

    /**
     * Clicks in range, joined to sessions so the dashboard filters apply.
     */
    private function clicks(WebAnalyticsFilters $filters): Builder
    {
        $query = WebClick::query()
            ->join('web_sessions', 'web_sessions.id', '=', 'web_clicks.web_session_id')
            ->whereBetween('web_clicks.occurred_at', [$filters->fromUtc(), $filters->toUtc()]);

        $this->query->applyFilters($query, $filters->withDimensions(array_diff_key($filters->dimensions, [WebDimensions::PAGE => true])));

        return $query->toBase();
    }
}
