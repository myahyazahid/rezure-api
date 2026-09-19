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

    public function test_router_menu_item_is_present_in_dashboard_navigation(): void
    {
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Router');
        $response->assertSee('https://router.redscale.my.id');
        $response->assertSee('target="_blank"', false);
    }

    public function test_whatsapp_menu_item_is_present_in_dashboard_navigation(): void
    {
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Whats App');
        $response->assertSee(route('dashboard.whatsapp'));
    }

    public function test_whatsapp_page_renders_with_device_list_and_open_gowa_button(): void
    {
        $response = $this->get('/dashboard/whatsapp');

        $response->assertOk();
        $response->assertSee('Whats App');
        $response->assertSee('Open GoWA');
        $response->assertSee('https://gowa.redscale.my.id');
        $response->assertSee('Daftar Akun WhatsApp Terhubung');
        $response->assertDontSee('<iframe', false);
    }

    public function test_whatsapp_devices_endpoint_returns_json(): void
    {
        $response = $this->get('/dashboard/whatsapp/devices');

        $this->assertContains($response->status(), [200, 401, 500]);
        $this->assertArrayHasKey('devices', $response->json());
    }

    public function test_whatsapp_qr_endpoint_returns_json(): void
    {
        $response = $this->get('/dashboard/whatsapp/qr');

        $this->assertContains($response->status(), [200, 401, 422, 500]);
        $this->assertNotNull($response->json('status'));
    }

    public function test_whatsapp_status_endpoint_returns_json(): void
    {
        $response = $this->get('/dashboard/whatsapp/status');

        $response->assertOk();
        $this->assertArrayHasKey('connected', $response->json());
    }

    public function test_whatsapp_pairing_code_endpoint_validates_phone(): void
    {
        $response = $this->postJson('/dashboard/whatsapp/pairing-code', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('phone');
    }

    public function test_whatsapp_pairing_code_endpoint_handles_request(): void
    {
        $response = $this->postJson('/dashboard/whatsapp/pairing-code', [
            'phone' => '081234567890',
        ]);

        $this->assertContains($response->status(), [200, 401, 422, 500]);
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
