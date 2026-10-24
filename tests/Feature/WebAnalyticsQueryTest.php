<?php

namespace Tests\Feature;

use App\Models\WebDailyDimensionSummary;
use App\Models\WebDailySummary;
use App\Models\WebEvent;
use App\Models\WebGoal;
use App\Models\WebPageview;
use App\Models\WebSession;
use App\Models\WebVisitor;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WebAnalyticsQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 15:00 WIB on 24 Oct.
        Carbon::setTestNow('2026-10-24 08:00:00');
    }

    public function test_the_summary_counts_raw_rows_when_no_rollups_exist(): void
    {
        $this->visit('2026-10-20 03:00:00', ['/', '/download'], download: true);
        $this->visit('2026-10-21 03:00:00', ['/guide']);

        $summary = $this->analytics()->summary($this->filters(['period' => '7d']));

        $this->assertSame(2, $summary['visitors']);
        $this->assertSame(2, $summary['sessions']);
        $this->assertSame(3, $summary['pageviews']);
        $this->assertSame(1, $summary['downloads']);
        $this->assertSame(50.0, $summary['bounce_rate']);
        $this->assertSame(1.5, $summary['views_per_session']);
    }

    public function test_rollups_give_the_same_numbers_as_the_raw_rows(): void
    {
        $returning = WebVisitor::factory()->create();

        $this->visit('2026-10-19 02:00:00', ['/', '/download'], download: true, visitor: $returning);
        $this->visit('2026-10-21 05:00:00', ['/guide'], visitor: $returning);
        $this->visit('2026-10-22 12:00:00', ['/blog', '/blog/posts/hello'], channel: 'Social');
        $this->visit('2026-10-24 06:00:00', ['/']);

        $filters = $this->filters(['period' => '7d']);
        $query = $this->analytics();
        $this->assertFalse($query->canUseRollups($filters));

        $raw = [$query->summary($filters), $query->timeseries($filters), $query->breakdown($filters, 'channel'), $query->pages($filters)];

        $this->artisan('app:generate-web-summaries', ['--from' => '2026-10-18', '--to' => '2026-10-23'])->assertSuccessful();

        $query = $this->analytics();
        $this->assertTrue($query->canUseRollups($filters));

        $rolled = [$query->summary($filters), $query->timeseries($filters), $query->breakdown($filters, 'channel'), $query->pages($filters)];

        $this->assertEquals($raw, $rolled);
    }

    public function test_rerunning_the_rollup_does_not_double_count(): void
    {
        $this->visit('2026-10-22 12:00:00', ['/', '/guide']);

        $this->artisan('app:generate-web-summaries', ['--date' => '2026-10-22'])->assertSuccessful();
        $this->artisan('app:generate-web-summaries', ['--date' => '2026-10-22'])->assertSuccessful();

        $this->assertSame(2, WebDailySummary::sole()->pageviews);
        $this->assertSame(1, WebDailyDimensionSummary::where('dimension', 'channel')->sole()->sessions);
    }

    public function test_the_default_run_rolls_up_yesterday_and_the_day_before(): void
    {
        $this->artisan('app:generate-web-summaries')->assertSuccessful();

        $this->assertSame(['2026-10-22', '2026-10-23'], WebDailySummary::orderBy('date')->get()->map(fn (WebDailySummary $row): string => $row->date->toDateString())->all());
    }

    public function test_unique_visitors_are_not_summed_across_days(): void
    {
        $visitor = WebVisitor::factory()->create();

        foreach (['2026-10-20', '2026-10-21', '2026-10-22'] as $day) {
            $this->visit($day.' 04:00:00', ['/'], visitor: $visitor);
        }

        $this->artisan('app:generate-web-summaries', ['--from' => '2026-10-20', '--to' => '2026-10-23']);

        $summary = $this->analytics()->summary($this->filters(['period' => '7d']));

        $this->assertSame(3, $summary['sessions']);
        $this->assertSame(1, $summary['visitors']);
    }

    public function test_days_follow_the_display_timezone(): void
    {
        // 20:00 UTC on the 22nd is 03:00 WIB on the 23rd.
        $this->visit('2026-10-22 20:00:00', ['/']);

        $series = collect($this->analytics()->timeseries($this->filters(['period' => '7d'])))->keyBy('key');

        $this->assertSame(0, $series['2026-10-22']['sessions']);
        $this->assertSame(1, $series['2026-10-23']['sessions']);
        $this->assertCount(7, $series);
    }

    public function test_dimension_filters_narrow_every_metric(): void
    {
        $this->visit('2026-10-22 04:00:00', ['/'], country: 'ID');
        $this->visit('2026-10-22 05:00:00', ['/', '/download'], country: 'MY');

        $summary = $this->analytics()->summary($this->filters(['period' => '7d', 'f' => ['country' => 'MY']]));

        $this->assertSame(1, $summary['sessions']);
        $this->assertSame(2, $summary['pageviews']);
    }

    public function test_a_page_filter_keeps_sessions_that_viewed_the_page(): void
    {
        $this->visit('2026-10-22 04:00:00', ['/', '/download']);
        $this->visit('2026-10-22 05:00:00', ['/guide']);

        $filters = $this->filters(['period' => '7d', 'f' => ['page' => '/download']]);

        $this->assertSame(1, $this->analytics()->summary($filters)['sessions']);
        $this->assertSame(1, $this->analytics()->summary($filters)['pageviews']);
    }

    public function test_a_wildcard_page_filter_matches_a_section(): void
    {
        $this->visit('2026-10-22 04:00:00', ['/guide/installation']);
        $this->visit('2026-10-22 05:00:00', ['/guide/first-run']);
        $this->visit('2026-10-22 06:00:00', ['/blog']);

        $this->assertSame(2, $this->analytics()->summary($this->filters(['period' => '7d', 'f' => ['page' => '/guide/*']]))['sessions']);
    }

    public function test_a_goal_filter_keeps_converted_sessions(): void
    {
        $goal = WebGoal::factory()->create();

        $this->visit('2026-10-22 04:00:00', ['/download'], download: true);
        $this->visit('2026-10-22 05:00:00', ['/']);

        $this->assertSame(1, $this->analytics()->summary($this->filters(['period' => '7d', 'f' => ['goal' => (string) $goal->id]]))['sessions']);
    }

    public function test_a_breakdown_ranks_values_and_shows_missing_ones_as_none(): void
    {
        $this->visit('2026-10-22 04:00:00', ['/'], channel: 'Social');
        $this->visit('2026-10-22 05:00:00', ['/'], channel: 'Social');
        $this->visit('2026-10-22 06:00:00', ['/'], channel: null);

        $rows = $this->analytics()->breakdown($this->filters(['period' => '7d']), 'channel');

        $this->assertSame(['Social', WebAnalyticsQuery::NONE], array_column($rows, 'value'));
        $this->assertSame([2, 1], array_column($rows, 'sessions'));
        $this->assertSame(66.7, $rows[0]['share']);

        $filtered = $this->analytics()->summary($this->filters(['period' => '7d', 'f' => ['channel' => WebAnalyticsQuery::NONE]]));
        $this->assertSame(1, $filtered['sessions']);
    }

    public function test_comparison_reports_the_change_against_the_previous_period(): void
    {
        $this->visit('2026-10-12 04:00:00', ['/']);
        $this->visit('2026-10-20 04:00:00', ['/']);
        $this->visit('2026-10-21 04:00:00', ['/']);

        $query = $this->analytics();
        $compared = $query->compared($this->filters(['period' => '7d']), fn (WebAnalyticsFilters $filters): array => $query->summary($filters));

        $this->assertSame(2, $compared['current']['sessions']);
        $this->assertSame(1, $compared['previous']['sessions']);
        $this->assertSame(100.0, $compared['change']['sessions']);
    }

    public function test_custom_ranges_are_read_from_the_query_string(): void
    {
        $filters = $this->filters(['period' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-12']);

        $this->assertSame(3, $filters->days());
        $this->assertSame('2026-10-09 17:00:00', $filters->fromUtc()->toDateTimeString());
        $this->assertSame('day', $filters->granularity());
        $this->assertSame('hour', $this->filters(['period' => 'today'])->granularity());
    }

    /**
     * @param  list<string>  $paths
     */
    private function visit(string $startedAtUtc, array $paths, bool $download = false, ?WebVisitor $visitor = null, ?string $channel = 'Direct', string $country = 'ID'): WebSession
    {
        $startedAt = Carbon::parse($startedAtUtc);
        $session = WebSession::factory()
            ->at($startedAt, 30 * count($paths))
            ->create([
                'web_visitor_id' => ($visitor ?? WebVisitor::factory()->create())->id,
                'pageviews_count' => count($paths),
                'is_bounce' => count($paths) < 2 && ! $download,
                'is_engaged' => count($paths) >= 2 || $download,
                'has_conversion' => $download,
                'entry_path' => $paths[0],
                'exit_path' => end($paths),
                'channel' => $channel,
                'country_code' => $country,
            ]);

        foreach ($paths as $index => $path) {
            WebPageview::factory()->create([
                'web_session_id' => $session->id,
                'path' => $path,
                'sequence' => $index + 1,
                'is_entry' => $index === 0,
                'is_exit' => $index === count($paths) - 1,
                'occurred_at' => $startedAt->copy()->addSeconds(30 * $index),
                'engaged_ms' => 10000,
                'max_scroll_pct' => 50,
            ]);
        }

        if ($download) {
            WebEvent::factory()->download()->create(['web_session_id' => $session->id, 'occurred_at' => $startedAt->copy()->addSeconds(5)]);
        }

        return $session;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function filters(array $query): WebAnalyticsFilters
    {
        return WebAnalyticsFilters::fromRequest(Request::create('/', 'GET', $query));
    }

    private function analytics(): WebAnalyticsQuery
    {
        return new WebAnalyticsQuery;
    }
}
