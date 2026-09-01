<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_devices_export_streams_a_csv(): void
    {
        Device::factory()->create();

        $response = $this->get('/dashboard/devices/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
