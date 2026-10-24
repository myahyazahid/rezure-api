<?php

namespace App\Services\WebAnalytics\Reports;

use App\Models\WebVisitor;
use App\Services\WebAnalytics\LocalTime;
use App\Services\WebAnalytics\TrafficSourceClassifier;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;

/**
 * Website › Acquisition: where visits — and downloads — come from.
 */
class AcquisitionReport
{
    /**
     * Source groups shown as their own cards, each a channel.
     *
     * @var array<string, string>
     */
    public const SOURCE_GROUPS = [
        'search' => TrafficSourceClassifier::ORGANIC_SEARCH,
        'social' => TrafficSourceClassifier::SOCIAL,
        'developer' => TrafficSourceClassifier::DEVELOPER_COMMUNITIES,
        'ai' => TrafficSourceClassifier::AI_ASSISTANTS,
    ];

    public function __construct(private readonly WebAnalyticsQuery $query) {}

    /**
     * @return array<string, mixed>
     */
    public function build(WebAnalyticsFilters $filters): array
    {
        return $this->query->remember($filters, 'acquisition', function () use ($filters): array {
            $groups = [];

            foreach (self::SOURCE_GROUPS as $key => $channel) {
                $groups[$key] = [
                    'channel' => $channel,
                    'rows' => $filters->filter('channel') !== null && $filters->filter('channel') !== $channel
                        ? []
                        : $this->query->breakdown($filters->withDimensions([...$filters->dimensions, 'channel' => $channel]), 'source', 8),
                ];
            }

            return [
                'channels' => $this->query->breakdown($filters, 'channel', 12),
                'channelTrend' => $this->channelTrend($filters),
                'sources' => $this->query->breakdown($filters, 'source', 15),
                'referrers' => $this->referrerUrls($filters),
                'groups' => $groups,
                'campaigns' => $this->campaigns($filters),
                'landingByChannel' => $this->landingPagesByChannel($filters),
                'attribution' => $this->attribution($filters),
            ];
        });
    }

    /**
     * Sessions per bucket for the five biggest channels.
     *
     * @return array{labels: list<string>, series: list<array{name: string, data: list<int>}>}
     */
    private function channelTrend(WebAnalyticsFilters $filters): array
    {
        $granularity = $filters->granularity();
        $labels = $this->query->bucketLabels($filters, $granularity);
        $top = array_column($this->query->breakdown($filters, 'channel', 5), 'value');
        $bucket = LocalTime::bucket('web_sessions.started_at', $granularity);

        $counts = $this->query->sessions($filters)
            ->toBase()
            ->selectRaw("{$bucket} as bucket, COALESCE(channel, '".WebAnalyticsQuery::NONE."') as channel, COUNT(*) as total")
            ->groupByRaw("{$bucket}, COALESCE(channel, '".WebAnalyticsQuery::NONE."')")
            ->get()
            ->groupBy('channel');

        return [
            'labels' => array_values($labels),
            'series' => array_map(fn (string $channel): array => [
                'name' => $channel,
                'data' => array_map(
                    fn (string $key): int => (int) ($counts->get($channel)?->firstWhere('bucket', $key)?->total ?? 0),
                    array_keys($labels),
                ),
            ], $top),
        ];
    }

    /**
     * The exact pages that linked to the site.
     *
     * @return list<array{url: string, host: string, sessions: int, visitors: int}>
     */
    private function referrerUrls(WebAnalyticsFilters $filters): array
    {
        return $this->query->sessions($filters)
            ->toBase()
            ->whereNotNull('referrer_url')
            ->selectRaw('referrer_url, referrer_host, COUNT(*) as sessions, COUNT(DISTINCT web_visitor_id) as visitors')
            ->groupBy('referrer_url', 'referrer_host')
            ->orderByDesc('sessions')
            ->limit(25)
            ->get()
            ->map(fn (object $row): array => [
                'url' => $row->referrer_url,
                'host' => $row->referrer_host,
                'sessions' => (int) $row->sessions,
                'visitors' => (int) $row->visitors,
            ])
            ->all();
    }

    /**
     * @return list<array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, utm_term: ?string, utm_content: ?string, sessions: int, visitors: int, bounce_rate: float, conversions: int}>
     */
    private function campaigns(WebAnalyticsFilters $filters): array
    {
        return $this->query->sessions($filters)
            ->toBase()
            ->where(fn ($query) => $query->whereNotNull('utm_source')->orWhereNotNull('utm_campaign')->orWhereNotNull('utm_medium'))
            ->selectRaw('utm_source, utm_medium, utm_campaign, utm_term, utm_content, COUNT(*) as sessions, COUNT(DISTINCT web_visitor_id) as visitors, SUM(is_bounce) as bounces, SUM(has_conversion) as conversions')
            ->groupBy('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content')
            ->orderByDesc('sessions')
            ->limit(30)
            ->get()
            ->map(fn (object $row): array => [
                'utm_source' => $row->utm_source,
                'utm_medium' => $row->utm_medium,
                'utm_campaign' => $row->utm_campaign,
                'utm_term' => $row->utm_term,
                'utm_content' => $row->utm_content,
                'sessions' => (int) $row->sessions,
                'visitors' => (int) $row->visitors,
                'bounce_rate' => $row->sessions > 0 ? round($row->bounces / $row->sessions * 100, 1) : 0.0,
                'conversions' => (int) $row->conversions,
            ])
            ->all();
    }

    /**
     * @return array<string, list<array{path: string, sessions: int}>>
     */
    private function landingPagesByChannel(WebAnalyticsFilters $filters): array
    {
        return $this->query->sessions($filters)
            ->toBase()
            ->whereNotNull('entry_path')
            ->selectRaw("COALESCE(channel, '".WebAnalyticsQuery::NONE."') as channel, entry_path, COUNT(*) as sessions")
            ->groupByRaw("COALESCE(channel, '".WebAnalyticsQuery::NONE."'), entry_path")
            ->orderByDesc('sessions')
            ->get()
            ->groupBy('channel')
            ->map(fn ($rows) => $rows->take(5)->map(fn (object $row): array => ['path' => $row->entry_path, 'sessions' => (int) $row->sessions])->values()->all())
            ->all();
    }

    /**
     * Downloads credited to a channel two ways: the channel of the visit the
     * download happened in (last touch) vs the channel the visitor first
     * arrived through (first touch).
     *
     * @return list<array{channel: string, last_touch: int, first_touch: int}>
     */
    private function attribution(WebAnalyticsFilters $filters): array
    {
        $lastTouch = $this->query->sessions($filters)
            ->toBase()
            ->where('has_conversion', true)
            ->selectRaw("COALESCE(channel, '".WebAnalyticsQuery::NONE."') as channel, COUNT(DISTINCT web_visitor_id) as total")
            ->groupByRaw("COALESCE(channel, '".WebAnalyticsQuery::NONE."')")
            ->pluck('total', 'channel');

        $firstTouch = WebVisitor::query()
            ->whereBetween('first_download_at', [$filters->fromUtc(), $filters->toUtc()])
            ->whereIn('id', $this->query->sessions($filters)->select('web_visitor_id'))
            ->selectRaw("COALESCE(first_channel, '".WebAnalyticsQuery::NONE."') as channel, COUNT(*) as total")
            ->groupByRaw("COALESCE(first_channel, '".WebAnalyticsQuery::NONE."')")
            ->toBase()
            ->pluck('total', 'channel');

        return collect($lastTouch->keys())
            ->merge($firstTouch->keys())
            ->unique()
            ->map(fn (string $channel): array => [
                'channel' => $channel,
                'last_touch' => (int) ($lastTouch[$channel] ?? 0),
                'first_touch' => (int) ($firstTouch[$channel] ?? 0),
            ])
            ->sortByDesc(fn (array $row): int => $row['last_touch'] + $row['first_touch'])
            ->values()
            ->all();
    }
}
