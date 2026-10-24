<?php

namespace App\Services\WebAnalytics\Reports;

use App\Models\Blog;
use App\Models\WebPageview;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use App\Services\WebAnalytics\WebDimensions;
use Illuminate\Database\Query\JoinClause;

/**
 * Website › Pages (detail) and Website › Content (blog, docs, 404s).
 */
class ContentReport
{
    /**
     * Where the website renders a blog post (VitePress dynamic route
     * blog/posts/[slug].md).
     */
    public const BLOG_POST_PREFIX = '/blog/posts/';

    public function __construct(private readonly WebAnalyticsQuery $query) {}

    /**
     * Everything about one page: its numbers, trend, where its visitors
     * came from and went next.
     *
     * @return array<string, mixed>
     */
    public function page(WebAnalyticsFilters $filters, string $path): array
    {
        $pageFilters = $filters->withDimensions([...$filters->dimensions, WebDimensions::PAGE => $path]);

        return $this->query->remember($pageFilters, 'page-detail', function () use ($filters, $pageFilters, $path): array {
            $stats = $this->query->pages($pageFilters, 'pageviews', 1)[0] ?? null;

            $views = $this->query->pageviews($pageFilters)
                ->get(['web_pageviews.max_scroll_pct', 'web_pageviews.lcp_ms', 'web_pageviews.inp_ms', 'web_pageviews.cls']);

            return [
                'path' => $path,
                'stats' => $stats,
                'compared' => $this->query->compared($pageFilters, fn (WebAnalyticsFilters $period): array => $this->query->pages($period, 'pageviews', 1)[0] ?? ['pageviews' => 0, 'visitors' => 0, 'avg_engaged_seconds' => 0, 'avg_scroll' => 0, 'entries' => 0, 'exit_rate' => 0, 'bounce_rate' => 0, 'exits' => 0, 'bounces' => 0, 'not_found' => 0, 'path' => $path]),
                'trend' => $this->query->timeseries($pageFilters),
                'sources' => $this->query->breakdown($pageFilters, 'source', 8),
                'devices' => $this->query->breakdown($pageFilters, 'device_type', 5),
                'previous' => $this->neighbours($filters, $path, -1),
                'next' => $this->neighbours($filters, $path, 1),
                'scroll' => $this->scrollDistribution($views->pluck('max_scroll_pct')->all()),
                'vitals' => [
                    'lcp' => WebAnalyticsQuery::percentile($views->pluck('lcp_ms'), 75),
                    'inp' => WebAnalyticsQuery::percentile($views->pluck('inp_ms'), 75),
                    'cls' => WebAnalyticsQuery::percentile($views->pluck('cls'), 75),
                ],
            ];
        });
    }

    /**
     * The page viewed right before (`-1`) or right after (`+1`) `$path` in
     * the same session. Sessions that started (or ended) on `$path` show as
     * "(entrance)" / "(exit)".
     *
     * @return list<array{path: string, count: int, share: float}>
     */
    public function neighbours(WebAnalyticsFilters $filters, string $path, int $offset): array
    {
        $rows = $this->query->pageviews($filters->withDimensions($this->withoutPage($filters)))
            ->toBase()
            ->where('web_pageviews.path', $path)
            ->leftJoin('web_pageviews as neighbour', function (JoinClause $join) use ($offset): void {
                $join->on('neighbour.web_session_id', '=', 'web_pageviews.web_session_id')
                    ->whereRaw('neighbour.sequence = web_pageviews.sequence + ?', [$offset]);
            })
            ->selectRaw("COALESCE(neighbour.path, '".($offset < 0 ? '(entrance)' : '(exit)')."') as neighbour_path, COUNT(*) as total")
            ->groupBy('neighbour_path')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $total = max(1, (int) $rows->sum('total'));

        return $rows->map(fn (object $row): array => [
            'path' => $row->neighbour_path,
            'count' => (int) $row->total,
            'share' => round($row->total / $total * 100, 1),
        ])->all();
    }

    /**
     * Blog posts (joined to the `blogs` table by slug) with how they were
     * read. "First 7 days" counts views from publish time, so an old post
     * and a new one can be compared fairly.
     *
     * @return list<array<string, mixed>>
     */
    public function blog(WebAnalyticsFilters $filters): array
    {
        return $this->query->remember($filters, 'content-blog', function () use ($filters): array {
            $pages = collect($this->query->pages($filters, 'pageviews', 200, self::BLOG_POST_PREFIX))->keyBy('path');

            $readThroughs = $this->query->events($filters, 'blog_read_complete')
                ->toBase()
                ->selectRaw('web_events.label as slug, COUNT(*) as total')
                ->groupBy('web_events.label')
                ->pluck('total', 'slug');

            return Blog::query()
                ->where('status', 'published')
                ->whereNotNull('published_at')
                ->orderByDesc('published_at')
                ->get(['id', 'title', 'slug', 'published_at'])
                ->map(function (Blog $blog) use ($pages, $readThroughs): array {
                    $page = $pages[self::BLOG_POST_PREFIX.$blog->slug] ?? null;
                    $views = $page['pageviews'] ?? 0;

                    return [
                        'title' => $blog->title,
                        'slug' => $blog->slug,
                        'path' => self::BLOG_POST_PREFIX.$blog->slug,
                        'published_at' => $blog->published_at,
                        'views' => $views,
                        'readers' => $page['visitors'] ?? 0,
                        'avg_engaged_seconds' => $page['avg_engaged_seconds'] ?? 0,
                        'avg_scroll' => $page['avg_scroll'] ?? 0,
                        'read_through_rate' => $views > 0 ? round(min($views, (int) ($readThroughs[$blog->slug] ?? 0)) / $views * 100, 1) : 0.0,
                        'first_week_views' => WebPageview::query()
                            ->where('path', self::BLOG_POST_PREFIX.$blog->slug)
                            ->whereBetween('occurred_at', [$blog->published_at, $blog->published_at->copy()->addDays(7)])
                            ->count(),
                    ];
                })
                ->all();
        });
    }

    /**
     * What visitors searched the docs for, and which searches were the last
     * thing they did on the site — a hint the docs didn't have the answer.
     *
     * @return list<array{query: string, searches: int, sessions: int, exits: int, exit_rate: float}>
     */
    public function docsSearches(WebAnalyticsFilters $filters, int $limit = 25): array
    {
        return $this->query->events($filters, 'docs_search')
            ->toBase()
            ->leftJoin('web_pageviews as search_page', 'search_page.id', '=', 'web_events.web_pageview_id')
            ->selectRaw('LOWER(web_events.label) as search, COUNT(*) as searches, COUNT(DISTINCT web_events.web_session_id) as sessions, SUM(CASE WHEN search_page.is_exit = 1 THEN 1 ELSE 0 END) as exits')
            ->whereNotNull('web_events.label')
            ->groupByRaw('LOWER(web_events.label)')
            ->orderByDesc('searches')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'query' => $row->search,
                'searches' => (int) $row->searches,
                'sessions' => (int) $row->sessions,
                'exits' => (int) $row->exits,
                'exit_rate' => $row->searches > 0 ? round($row->exits / $row->searches * 100, 1) : 0.0,
            ])
            ->all();
    }

    /**
     * 404s grouped by the missing path, with the page that linked there —
     * the list of broken links to fix.
     *
     * @return list<array{path: string, hits: int, referrers: list<string>, last_seen: string}>
     */
    public function notFound(WebAnalyticsFilters $filters, int $limit = 25): array
    {
        return $this->query->pageviews($filters)
            ->toBase()
            ->where('web_pageviews.is_not_found', true)
            ->selectRaw('web_pageviews.path as path, COUNT(*) as hits, MAX(web_pageviews.occurred_at) as last_seen')
            ->groupBy('web_pageviews.path')
            ->orderByDesc('hits')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'path' => $row->path,
                'hits' => (int) $row->hits,
                'referrers' => $this->query->pageviews($filters)
                    ->toBase()
                    ->where('web_pageviews.is_not_found', true)
                    ->where('web_pageviews.path', $row->path)
                    ->whereNotNull('web_pageviews.referrer_url')
                    ->selectRaw('web_pageviews.referrer_url as referrer, COUNT(*) as total')
                    ->groupBy('web_pageviews.referrer_url')
                    ->orderByDesc('total')
                    ->limit(3)
                    ->pluck('referrer')
                    ->all(),
                'last_seen' => $row->last_seen,
            ])
            ->all();
    }

    /**
     * @param  list<int|null>  $scrolls
     * @return list<array{label: string, count: int}>
     */
    private function scrollDistribution(array $scrolls): array
    {
        $buckets = ['0–25%' => 0, '25–50%' => 0, '50–75%' => 0, '75–90%' => 0, '90–100%' => 0];

        foreach ($scrolls as $scroll) {
            $scroll = (int) $scroll;
            $key = match (true) {
                $scroll < 25 => '0–25%',
                $scroll < 50 => '25–50%',
                $scroll < 75 => '50–75%',
                $scroll < 90 => '75–90%',
                default => '90–100%',
            };
            $buckets[$key]++;
        }

        return array_map(fn (string $label, int $count): array => ['label' => $label, 'count' => $count], array_keys($buckets), $buckets);
    }

    /**
     * @return array<string, string>
     */
    private function withoutPage(WebAnalyticsFilters $filters): array
    {
        $dimensions = $filters->dimensions;
        unset($dimensions[WebDimensions::PAGE]);

        return $dimensions;
    }
}
