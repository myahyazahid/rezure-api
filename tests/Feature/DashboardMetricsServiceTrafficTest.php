<?php

namespace Tests\Feature;

use App\Models\CountryTrafficSummary;
use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\Event;
use App\Models\HourlyTrafficSummary;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 3.3/3.4: the dashboard aggregation methods backing Traffic, Geography,
 * and Behavior. These read from the summary tables (Fase 3.2) where those
 * exist, so tests seed HourlyTrafficSummary/CountryTrafficSummary directly
 * rather than raw events, matching what the dashboard actually queries.
 */
class DashboardMetricsServiceTrafficTest extends TestCase
{
    use RefreshDatabase;

    private DashboardMetricsService $metrics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metrics = app(DashboardMetricsService::class);
    }

    public function test_hourly_traffic_heatmap_sums_summary_rows_and_normalizes_intensity(): void
    {
        $today = now()->toDateString();
        HourlyTrafficSummary::factory()->state(['date' => $today, 'hour' => 9, 'event_count' => 50])->create();
        HourlyTrafficSummary::factory()->state(['date' => $today, 'hour' => 14, 'event_count' => 100])->create();

        $heatmap = $this->metrics->hourlyTrafficHeatmap(7);

        $this->assertCount(24, $heatmap);
        $this->assertSame(50, $heatmap[9]['total']);
        $this->assertSame(100, $heatmap[14]['total']);
        $this->assertSame(0.5, $heatmap[9]['intensity']);
        $this->assertSame(1.0, $heatmap[14]['intensity']);
        $this->assertSame(0, $heatmap[0]['total']);
    }

    public function test_traffic_by_day_of_week_buckets_daily_totals_by_weekday(): void
    {
        // 2026-08-31 is a Monday, 2026-09-01 a Tuesday.
        HourlyTrafficSummary::factory()->state(['date' => '2026-08-31', 'hour' => 10, 'event_count' => 30])->create();
        HourlyTrafficSummary::factory()->state(['date' => '2026-09-01', 'hour' => 11, 'event_count' => 10])->create();

        $byDay = collect($this->metrics->trafficByDayOfWeek(30))->keyBy('day');

        $this->assertSame(30, $byDay['Mon']['total']);
        $this->assertSame(10, $byDay['Tue']['total']);
        $this->assertSame(75.0, $byDay['Mon']['percentage']);
        $this->assertSame(0, $byDay['Wed']['total']);
    }

    public function test_geographic_distribution_sums_country_summary_with_full_names_and_percentages(): void
    {
        CountryTrafficSummary::factory()->state(['date' => now()->toDateString(), 'country_code' => 'ID', 'device_count' => 30])->create();
        CountryTrafficSummary::factory()->state(['date' => now()->subDay()->toDateString(), 'country_code' => 'ID', 'device_count' => 10])->create();
        CountryTrafficSummary::factory()->state(['date' => now()->toDateString(), 'country_code' => 'US', 'device_count' => 10])->create();

        $distribution = $this->metrics->geographicDistribution(30);

        $this->assertSame('ID', $distribution[0]['country_code']);
        $this->assertSame('Indonesia', $distribution[0]['country_name']);
        $this->assertSame(40, $distribution[0]['total']);
        $this->assertSame(80.0, $distribution[0]['percentage']);
        $this->assertSame('United States of America', $distribution[1]['country_name']);
    }

    public function test_country_growth_trend_returns_the_top_n_countries_as_daily_series(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        CountryTrafficSummary::factory()->state(['date' => $yesterday, 'country_code' => 'ID', 'device_count' => 5])->create();
        CountryTrafficSummary::factory()->state(['date' => $today, 'country_code' => 'ID', 'device_count' => 8])->create();
        CountryTrafficSummary::factory()->state(['date' => $today, 'country_code' => 'US', 'device_count' => 1])->create();

        $growth = $this->metrics->countryGrowthTrend(2, topN: 1);

        $this->assertCount(2, $growth['labels']);
        $this->assertCount(1, $growth['series']);
        $this->assertSame('ID', $growth['series'][0]['code']);
        $this->assertSame([5, 8], $growth['series'][0]['data']);
    }

    public function test_session_length_distribution_buckets_durations_into_the_right_ranges(): void
    {
        $device = Device::factory()->create();

        DeviceSession::factory()->for($device)->create(['duration_seconds' => 30, 'started_at' => now()->subDay()]);
        DeviceSession::factory()->for($device)->create(['duration_seconds' => 120, 'started_at' => now()->subDay()]);
        DeviceSession::factory()->for($device)->create(['duration_seconds' => 5000, 'started_at' => now()->subDay()]);
        // Outside the period — must not be counted.
        DeviceSession::factory()->for($device)->create(['duration_seconds' => 30, 'started_at' => now()->subDays(60)]);
        // No duration yet (still active) — must not be counted.
        DeviceSession::factory()->for($device)->create(['duration_seconds' => null, 'started_at' => now()->subDay()]);

        $buckets = collect($this->metrics->sessionLengthDistribution(30))->keyBy('label');

        $this->assertSame(1, $buckets['< 1m']['count']);
        $this->assertSame(1, $buckets['1–5m']['count']);
        $this->assertSame(1, $buckets['1–2h']['count']);
        $this->assertSame(0, $buckets['2h+']['count']);
        $this->assertSame(3, collect($buckets)->sum('count'));
    }

    public function test_new_vs_returning_devices_classifies_first_seen_day_as_new_and_later_activity_as_returning(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02 10:00:00'));

        $device = Device::factory()->create(['first_seen_at' => Carbon::parse('2026-08-30 08:00:00')]);

        Event::factory()->for($device)->create(['occurred_at' => Carbon::parse('2026-08-30 09:00:00')]);
        Event::factory()->for($device)->create(['occurred_at' => Carbon::parse('2026-08-31 09:00:00')]);

        $trend = collect($this->metrics->newVsReturningDevices(5))->keyBy('date');

        $this->assertSame(1, $trend['2026-08-30']['new']);
        $this->assertSame(0, $trend['2026-08-30']['returning']);
        $this->assertSame(0, $trend['2026-08-31']['new']);
        $this->assertSame(1, $trend['2026-08-31']['returning']);

        Carbon::setTestNow();
    }

    public function test_cohort_retention_reports_the_percentage_of_devices_still_active_each_week_offset(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        $cohortWeekStart = now()->subWeek()->startOfWeek();

        $stillActive = Device::factory()->create(['first_seen_at' => $cohortWeekStart->copy()->addDay()]);
        $churned = Device::factory()->create(['first_seen_at' => $cohortWeekStart->copy()->addDay()]);

        Event::factory()->for($stillActive)->create(['occurred_at' => $cohortWeekStart->copy()->addDay()]);
        Event::factory()->for($stillActive)->create(['occurred_at' => $cohortWeekStart->copy()->addWeek()->addDay()]);
        Event::factory()->for($churned)->create(['occurred_at' => $cohortWeekStart->copy()->addDay()]);

        $result = $this->metrics->cohortRetention(2);

        $cohort = collect($result['cohorts'])->firstWhere('label', $cohortWeekStart->format('d M'));

        $this->assertNotNull($cohort);
        $this->assertSame(2, $cohort['size']);
        $this->assertSame(100.0, $cohort['retention'][0]);
        $this->assertSame(50.0, $cohort['retention'][1]);

        Carbon::setTestNow();
    }

    public function test_churn_summary_counts_devices_past_the_threshold_and_lists_the_quietest_first(): void
    {
        $active = Device::factory()->create(['last_seen_at' => now()->subDays(2)]);
        $churnedRecently = Device::factory()->create(['last_seen_at' => now()->subDays(31)]);
        $churnedLongAgo = Device::factory()->create(['last_seen_at' => now()->subDays(90)]);

        $summary = $this->metrics->churnSummary(thresholdDays: 30);

        $this->assertSame(2, $summary['count']);
        $this->assertSame(30, $summary['threshold_days']);
        $this->assertSame($churnedLongAgo->id, $summary['devices']->first()->id);
        $this->assertFalse($summary['devices']->contains('id', $active->id));
    }
}
