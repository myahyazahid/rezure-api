<?php

namespace Tests\Feature;

use App\Models\DeviceSession;
use App\Models\Event;
use App\Services\DashboardMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Torann\GeoIP\Facades\GeoIP;
use Torann\GeoIP\Location;

class TelemetryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_registers_a_device_and_opens_a_session(): void
    {
        $deviceId = fake()->uuid();
        $sessionId = fake()->uuid();

        $response = $this->postJson('/api/v1/telemetry/heartbeat', [
            'device_id' => $deviceId,
            'session_id' => $sessionId,
            'app_version' => '1.4.0',
            'os' => 'Windows 11',
            'os_version' => '23H2',
        ]);

        $response->assertStatus(202)->assertJson(['status' => 'queued']);

        $this->assertDatabaseHas('devices', ['device_id' => $deviceId, 'app_version' => '1.4.0']);
        $this->assertDatabaseHas('device_sessions', ['client_session_id' => $sessionId]);
    }

    public function test_repeated_heartbeats_update_the_same_session_instead_of_creating_new_ones(): void
    {
        $deviceId = fake()->uuid();
        $sessionId = fake()->uuid();

        $payload = [
            'device_id' => $deviceId,
            'session_id' => $sessionId,
            'app_version' => '1.4.0',
        ];

        $this->postJson('/api/v1/telemetry/heartbeat', $payload)->assertStatus(202);
        $this->postJson('/api/v1/telemetry/heartbeat', $payload)->assertStatus(202);

        $this->assertSame(1, DeviceSession::where('client_session_id', $sessionId)->count());
    }

    public function test_event_is_recorded_against_its_device(): void
    {
        $deviceId = fake()->uuid();

        $response = $this->postJson('/api/v1/telemetry/event', [
            'device_id' => $deviceId,
            'event_id' => fake()->uuid(),
            'event_type' => 'service.start',
            'event_name' => 'nginx',
            'app_version' => '1.4.0',
        ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('events', ['event_type' => 'service.start', 'event_name' => 'nginx']);
    }

    /**
     * The exact bodies `rezureapp` sends (see its docs/telemetry-contract.md),
     * end to end into the two dashboard sections that read them.
     */
    public function test_the_clients_stack_and_error_events_reach_the_dashboard(): void
    {
        $deviceId = fake()->uuid();
        $event = fn (array $fields): array => [
            'device_id' => $deviceId,
            'event_id' => fake()->uuid(),
            'app_version' => '3.0.0',
            'occurred_at' => now()->toRfc3339String(),
            ...$fields,
        ];

        $this->postJson('/api/v1/telemetry/event', $event([
            'event_type' => 'service.start',
            'event_name' => 'Nginx',
            'payload' => ['php_version' => '8.3.33', 'mariadb_version' => 'MariaDB 11.8.9'],
        ]))->assertStatus(202);
        $this->postJson('/api/v1/telemetry/event', $event([
            'event_type' => 'error.report',
            'event_name' => 'PortInUse',
            'payload' => ['action' => 'service.start', 'service' => 'nginx'],
        ]))->assertStatus(202);
        $this->postJson('/api/v1/telemetry/event', $event([
            'event_type' => 'error.report',
            'event_name' => 'ServiceCrashed',
            'payload' => ['action' => 'service.run', 'service' => 'php-8.0.30'],
        ]))->assertStatus(202);

        $metrics = app(DashboardMetricsService::class);

        $this->assertSame('PHP 8.3.33 + MariaDB 11.8.9', $metrics->topStackCombos(30)[0]['label']);
        $this->assertEqualsCanonicalizing(
            ['PortInUse', 'ServiceCrashed'],
            array_column($metrics->errorReports(30), 'error'),
        );
    }

    public function test_a_retried_event_with_the_same_event_id_is_not_duplicated(): void
    {
        $deviceId = fake()->uuid();
        $eventId = fake()->uuid();

        $payload = [
            'device_id' => $deviceId,
            'event_id' => $eventId,
            'event_type' => 'service.start',
            'event_name' => 'nginx',
            'app_version' => '1.4.0',
        ];

        $this->postJson('/api/v1/telemetry/event', $payload)->assertStatus(202);
        $this->postJson('/api/v1/telemetry/event', $payload)->assertStatus(202);

        $this->assertSame(1, Event::where('client_event_id', $eventId)->count());
    }

    public function test_event_requires_a_device_id_and_app_version(): void
    {
        $response = $this->postJson('/api/v1/telemetry/event', [
            'event_id' => fake()->uuid(),
            'event_type' => 'service.start',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['device_id', 'app_version']);
    }

    public function test_heartbeat_rejects_a_non_uuid_device_id(): void
    {
        $response = $this->postJson('/api/v1/telemetry/heartbeat', [
            'device_id' => 'not-a-uuid',
            'session_id' => fake()->uuid(),
            'app_version' => '1.4.0',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['device_id']);
    }

    public function test_a_heartbeat_records_the_resolved_country_code_without_storing_the_raw_ip(): void
    {
        GeoIP::shouldReceive('getLocation')->once()->andReturn(new Location(['iso_code' => 'ID', 'default' => false]));

        $sessionId = fake()->uuid();

        $this->postJson('/api/v1/telemetry/heartbeat', [
            'device_id' => fake()->uuid(),
            'session_id' => $sessionId,
            'app_version' => '1.4.0',
        ], ['REMOTE_ADDR' => '203.0.113.10'])->assertStatus(202);

        $this->assertDatabaseHas('device_sessions', ['client_session_id' => $sessionId, 'country_code' => 'ID']);
    }

    public function test_an_event_records_the_resolved_country_code_without_storing_the_raw_ip(): void
    {
        GeoIP::shouldReceive('getLocation')->once()->andReturn(new Location(['iso_code' => 'ID', 'default' => false]));

        $eventId = fake()->uuid();

        $this->postJson('/api/v1/telemetry/event', [
            'device_id' => fake()->uuid(),
            'event_id' => $eventId,
            'event_type' => 'service.start',
            'app_version' => '1.4.0',
        ], ['REMOTE_ADDR' => '203.0.113.10'])->assertStatus(202);

        $this->assertDatabaseHas('events', ['client_event_id' => $eventId, 'country_code' => 'ID']);
    }
}
