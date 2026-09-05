<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_overview_page_renders_with_no_data(): void
    {
        $this->get('/dashboard')->assertOk();
    }

    public function test_overview_page_renders_with_data(): void
    {
        $device = Device::factory()->create();
        Event::factory()->for($device)->create();

        $this->get('/dashboard')->assertOk();
    }

    public function test_versions_features_errors_and_devices_pages_render(): void
    {
        $device = Device::factory()->create();
        Event::factory()->for($device)->create();

        $this->get('/dashboard/versions')->assertOk();
        $this->get('/dashboard/features')->assertOk();
        $this->get('/dashboard/errors')->assertOk();
        $this->get('/dashboard/devices')->assertOk();
    }

    public function test_traffic_geography_and_behavior_pages_render(): void
    {
        $device = Device::factory()->create();
        Event::factory()->for($device)->create(['country_code' => 'ID']);

        $this->get('/dashboard/traffic')->assertOk();
        $this->get('/dashboard/geography')->assertOk();
        $this->get('/dashboard/behavior')->assertOk();
    }

    public function test_traffic_geography_and_behavior_pages_render_with_no_data(): void
    {
        $this->get('/dashboard/traffic')->assertOk();
        $this->get('/dashboard/geography')->assertOk();
        $this->get('/dashboard/behavior')->assertOk();
    }

    public function test_technical_and_funnel_pages_render(): void
    {
        $device = Device::factory()->create();
        Event::factory()->for($device)->create([
            'event_type' => 'service.start',
            'payload' => ['php_version' => '8.3.2', 'mysql_version' => 'MySQL 8.0.35'],
        ]);

        $this->get('/dashboard/technical')->assertOk();
        $this->get('/dashboard/funnel')->assertOk();
    }

    public function test_technical_and_funnel_pages_render_with_no_data(): void
    {
        $this->get('/dashboard/technical')->assertOk();
        $this->get('/dashboard/funnel')->assertOk();
    }

    public function test_devices_export_streams_a_csv(): void
    {
        Device::factory()->create();

        $response = $this->get('/dashboard/devices/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
