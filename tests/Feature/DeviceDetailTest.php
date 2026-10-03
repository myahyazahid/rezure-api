<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DeviceSession;
use App\Models\Event;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeviceDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_devices_list_links_each_device_to_its_detail_page(): void
    {
        $device = Device::factory()->create();

        $this->get('/dashboard/devices')
            ->assertOk()
            ->assertSee(route('dashboard.devices.show', $device))
            ->assertSee('Detail');
    }

    public function test_detail_page_shows_usage_time_services_stack_and_errors(): void
    {
        $device = Device::factory()->create(['device_name' => 'Yahya']);

        DeviceSession::factory()->for($device)->create([
            'started_at' => now()->subDays(2),
            'last_heartbeat_at' => now()->subDays(2)->addHours(2),
            'ended_at' => now()->subDays(2)->addHours(2),
            'duration_seconds' => 2 * 3600,
        ]);
        DeviceSession::factory()->for($device)->create([
            'started_at' => now()->subHours(3),
            'last_heartbeat_at' => now()->subHours(2)->subMinutes(30),
            'ended_at' => null,
            'duration_seconds' => null,
        ]);

        Event::factory()->for($device)->type('service.start')->count(2)->create(['event_name' => 'Nginx', 'payload' => null]);
        Event::factory()->for($device)->type('service.start')->create([
            'event_name' => 'MariaDB',
            'payload' => ['php_version' => '8.3.33', 'mariadb_version' => 'MariaDB 11.8.9'],
            'occurred_at' => now()->subMinute(),
        ]);
        Event::factory()->for($device)->type('error.report')->create(['event_name' => 'PortInUse']);

        $this->get(route('dashboard.devices.show', $device))
            ->assertOk()
            ->assertSee('Yahya')
            ->assertSee('2h 30m')
            ->assertSee('Nginx')
            ->assertSee('MariaDB')
            ->assertSee('PHP 8.3.33 · MariaDB 11.8.9')
            ->assertSee('PortInUse')
            ->assertSee('Not closed cleanly');
    }

    public function test_detail_page_renders_for_a_device_with_no_activity(): void
    {
        $device = Device::factory()->create();

        $this->get(route('dashboard.devices.show', $device))
            ->assertOk()
            ->assertSee($device->short_id)
            ->assertSee('No sessions recorded yet.')
            ->assertSee('No services started from Rezure yet.');
    }

    public function test_detail_page_returns_404_for_an_unknown_device(): void
    {
        $this->get('/dashboard/devices/999999')->assertNotFound();
    }

    public function test_device_usage_counts_only_this_devices_sessions_and_buckets_days_in_wib(): void
    {
        $this->travelTo(Carbon::parse('2026-09-10 12:00:00'));

        $device = Device::factory()->create();
        $otherDevice = Device::factory()->create();

        // 20:00 UTC on 9 Sep is 03:00 WIB on 10 Sep.
        DeviceSession::factory()->for($device)->create([
            'started_at' => Carbon::parse('2026-09-09 20:00:00'),
            'last_heartbeat_at' => Carbon::parse('2026-09-09 21:30:00'),
            'ended_at' => Carbon::parse('2026-09-09 21:30:00'),
            'duration_seconds' => 5400,
        ]);
        DeviceSession::factory()->for($otherDevice)->create(['duration_seconds' => 9999]);

        $usage = app(DashboardMetricsService::class)->deviceUsage($device);

        $this->assertSame(5400, $usage['total_seconds']);
        $this->assertSame(5400, $usage['last_7_days_seconds']);
        $this->assertSame(1, $usage['session_count']);
        $this->assertCount(30, $usage['daily']);
        $this->assertSame(['label' => '10 Sep', 'hours' => 1.5], $usage['daily'][29]);
        $this->assertSame(0.0, $usage['daily'][28]['hours']);
    }
}
