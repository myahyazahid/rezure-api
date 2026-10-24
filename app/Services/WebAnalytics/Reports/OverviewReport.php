<?php

namespace App\Services\WebAnalytics\Reports;

use App\Models\Blog;
use App\Models\Release;
use App\Models\WebSession;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use Illuminate\Support\Carbon;

/**
 * Website › Overview: the headline KPIs with their comparison, the main
 * chart (with release / blog markers), and the top-N cards.
 */
class OverviewReport
{
    /**
     * Metrics the main chart can switch between, with how their values
     * read.
     *
     * @var array<string, array{label: string, format: string}>
     */
    public const CHART_METRICS = [
        'visitors' => ['label' => 'Visitors', 'format' => 'number'],
        'sessions' => ['label' => 'Sessions', 'format' => 'number'],
        'pageviews' => ['label' => 'Pageviews', 'format' => 'number'],
        'bounce_rate' => ['label' => 'Bounce rate', 'format' => 'percent'],
        'avg_duration' => ['label' => 'Visit duration', 'format' => 'duration'],
        'downloads' => ['label' => 'Downloads', 'format' => 'number'],
    ];

    public function __construct(private readonly WebAnalyticsQuery $query) {}

    /**
     * @return array<string, mixed>
     */
    public function build(WebAnalyticsFilters $filters): array
    {
        return $this->query->remember($filters, 'overview', function () use ($filters): array {
            $summary = $this->query->compared($filters, fn (WebAnalyticsFilters $period): array => $this->query->summary($period));
            $series = $this->query->timeseries($filters);
            $comparison = $filters->comparison();

            return [
                'summary' => $summary,
                'sparklines' => [
                    'visitors' => array_column($series, 'visitors'),
                    'sessions' => array_column($series, 'sessions'),
                    'pageviews' => array_column($series, 'pageviews'),
                    'downloads' => array_column($series, 'downloads'),
                ],
                'chart' => $this->chart($filters, $series, $comparison !== null ? $this->query->timeseries($comparison) : null),
                'pages' => $this->query->pages($filters, 'pageviews', 8),
                'entryPages' => $this->query->pages($filters, 'entries', 8),
                'channels' => $this->query->breakdown($filters, 'channel', 8),
                'sources' => $this->query->breakdown($filters, 'source', 8),
                'countries' => $this->query->breakdown($filters, 'country', 8),
                'devices' => $this->query->breakdown($filters, 'device_type', 5),
                'events' => $this->query->events($filters)
                    ->toBase()
                    ->selectRaw('web_events.name as name, COUNT(*) as total')
                    ->groupBy('web_events.name')
                    ->orderByDesc('total')
                    ->limit(8)
                    ->get()
                    ->map(fn (object $row): array => ['name' => $row->name, 'total' => (int) $row->total])
                    ->all(),
            ];
        });
    }

    /**
     * Visitors whose session saw activity in the last five minutes — the
     * "online now" number on the Overview and in the sidebar.
     */
    public function activeVisitors(): int
    {
        return WebSession::query()->where('ended_at', '>=', now()->subMinutes(5))->distinct()->count('web_visitor_id');
    }

    /**
     * @param  list<array<string, mixed>>  $series
     * @param  list<array<string, mixed>>|null  $previous
     * @return array<string, mixed>
     */
    private function chart(WebAnalyticsFilters $filters, array $series, ?array $previous): array
    {
        $chartSeries = [];

        foreach (self::CHART_METRICS as $metric => $meta) {
            $chartSeries[$metric] = [
                'label' => $meta['label'],
                'format' => $meta['format'],
                'current' => array_column($series, $metric),
                // Matched by position: bucket 3 of this period against bucket
                // 3 of the comparison period.
                'previous' => $previous !== null ? array_slice(array_column($previous, $metric), 0, count($series)) : null,
            ];
        }

        return [
            'labels' => array_column($series, 'label'),
            'series' => $chartSeries,
            'default' => 'visitors',
            'currentLabel' => $filters->label(),
            'previousLabel' => $filters->compare === 'year' ? 'Last year' : 'Previous period',
            'annotations' => $this->annotations($filters, array_column($series, 'key')),
        ];
    }

    /**
     * Releases and blog posts published inside the range, placed on the
     * bucket they fall in.
     *
     * @param  list<string>  $keys
     * @return list<array{index: int, label: string, kind: string}>
     */
    private function annotations(WebAnalyticsFilters $filters, array $keys): array
    {
        $granularity = $filters->granularity();
        $timezone = config('app.display_timezone');
        $positions = array_flip($keys);
        $annotations = [];

        $place = function (Carbon $at, string $label, string $kind) use (&$annotations, $positions, $granularity, $timezone): void {
            $local = $at->copy()->setTimezone($timezone);
            $key = match ($granularity) {
                'hour' => $local->format('Y-m-d H:00'),
                'week' => $local->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
                'month' => $local->format('Y-m-01'),
                default => $local->format('Y-m-d'),
            };

            if (isset($positions[$key])) {
                $annotations[] = ['index' => $positions[$key], 'label' => $label, 'kind' => $kind];
            }
        };

        Release::query()
            ->whereBetween('published_at', [$filters->fromUtc(), $filters->toUtc()])
            ->get(['version', 'published_at'])
            ->each(fn (Release $release) => $place($release->published_at, 'v'.$release->version, 'release'));

        Blog::query()
            ->where('status', 'published')
            ->whereBetween('published_at', [$filters->fromUtc(), $filters->toUtc()])
            ->get(['title', 'published_at'])
            ->each(fn (Blog $blog) => $place($blog->published_at, '✎ '.mb_strimwidth($blog->title, 0, 24, '…'), 'blog'));

        return $annotations;
    }
}
