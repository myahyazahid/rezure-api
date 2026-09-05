<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Event;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Fase 3.5/3.6/3.7: OS/version breakdown, the stack-combo chart, the
 * install->retention funnel, and the public aggregate endpoint's backing
 * data.
 */
class DashboardMetricsServiceTechnicalTest extends TestCase
{
    use RefreshDatabase;

    private DashboardMetricsService $metrics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->metrics = app(DashboardMetricsService::class);
    }

    public function test_os_version_breakdown_groups_by_the_exact_os_and_os_version_pair(): void
    {
        Device::factory()->count(2)->create(['os' => 'Windows 11', 'os_version' => '23H2']);
        Device::factory()->create(['os' => 'Windows 11', 'os_version' => '22H2']);
        Device::factory()->create(['os' => 'Windows 10', 'os_version' => '22H2']);

        $breakdown = collect($this->metrics->osVersionBreakdown())->keyBy('label');

        $this->assertSame(2, $breakdown['Windows 11 23H2']['count']);
        $this->assertSame(50.0, $breakdown['Windows 11 23H2']['percentage']);
        $this->assertSame(1, $breakdown['Windows 11 22H2']['count']);
        $this->assertSame(1, $breakdown['Windows 10 22H2']['count']);
    }

    public function test_top_stack_combos_reads_php_and_mysql_versions_from_service_start_payloads(): void
    {
        $device = Device::factory()->create();

        Event::factory()->for($device)->create([
            'event_type' => 'service.start',
            'payload' => ['php_version' => '8.3.2', 'mysql_version' => 'MySQL 8.0.35'],
        ]);
        Event::factory()->for($device)->create([
            'event_type' => 'service.start',
            'payload' => ['php_version' => '8.3.2', 'mysql_version' => 'MySQL 8.0.35'],
        ]);
        Event::factory()->for($device)->create([
            'event_type' => 'service.start',
            'payload' => ['php_version' => '8.2.15', 'mariadb_version' => 'MariaDB 11.2'],
        ]);
        // No version metadata — must be silently skipped, not counted as "unknown".
        Event::factory()->for($device)->create(['event_type' => 'service.start', 'payload' => null]);
        // Right shape, wrong event_type — must not be counted.
        Event::factory()->for($device)->create([
            'event_type' => 'service.stop',
            'payload' => ['php_version' => '8.3.2', 'mysql_version' => 'MySQL 8.0.35'],
        ]);

        $combos = collect($this->metrics->topStackCombos(30))->keyBy('label');

        $this->assertSame(2, $combos['PHP 8.3.2 + MySQL 8.0.35']['count']);
        $this->assertSame(1, $combos['PHP 8.2.15 + MariaDB 11.2']['count']);
        $this->assertCount(2, $combos);
    }

    public function test_top_stack_combos_returns_an_empty_list_when_no_payload_carries_version_metadata(): void
    {
        $device = Device::factory()->create();
        Event::factory()->for($device)->create(['event_type' => 'service.start', 'payload' => null]);

        $this->assertSame([], $this->metrics->topStackCombos(30));
    }

    public function test_product_funnel_reports_download_as_untracked_and_install_at_100_percent(): void
    {
        Device::factory()->count(3)->create();

        $funnel = $this->metrics->productFunnel();
        $stages = collect($funnel['stages'])->keyBy('label');

        $this->assertFalse($stages['Download']['tracked']);
        $this->assertNull($stages['Download']['count']);
        $this->assertSame(3, $stages['Install (first app_opened)']['count']);
        $this->assertSame(100.0, $stages['Install (first app_opened)']['percentage']);
    }

    public function test_product_funnel_only_measures_seven_day_retention_against_devices_old_enough_to_qualify(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));

        // Old enough (8 days) and still active on day 7+ — retained.
        $retained = Device::factory()->create(['first_seen_at' => now()->subDays(8)]);
        Event::factory()->for($retained)->create(['occurred_at' => now()->subDay()]);

        // Old enough but never came back after day 7 — churned.
        $churned = Device::factory()->create(['first_seen_at' => now()->subDays(8)]);
        Event::factory()->for($churned)->create(['occurred_at' => now()->subDays(8)]);

        // Installed 2 days ago — too young to measure, must not count as a drop-off.
        Device::factory()->create(['first_seen_at' => now()->subDays(2)]);

        $funnel = $this->metrics->productFunnel();
        $stages = collect($funnel['stages'])->keyBy('label');

        $this->assertSame(2, $funnel['eligible_for_7_day_measurement']);
        $this->assertSame(1, $stages['Active after 7 days']['count']);
        $this->assertSame(50.0, $stages['Active after 7 days']['percentage']);

        Carbon::setTestNow();
    }

    public function test_public_aggregate_stats_exposes_only_counts_no_granular_breakdowns(): void
    {
        $device = Device::factory()->create();
        Event::factory()->for($device)->create(['occurred_at' => now()->subHours(2)]);

        $stats = $this->metrics->publicAggregateStats();

        $this->assertSame(['active_devices', 'total_devices', 'generated_at'], array_keys($stats));
        $this->assertSame(['daily', 'weekly', 'monthly'], array_keys($stats['active_devices']));
        $this->assertSame(1, $stats['active_devices']['daily']);
        $this->assertSame(1, $stats['total_devices']);
    }
}
