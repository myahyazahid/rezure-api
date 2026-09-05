<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStatsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_aggregate_stats_without_any_authentication(): void
    {
        $device = Device::factory()->create();
        Event::factory()->for($device)->create();

        $response = $this->getJson('/api/v1/stats/public');

        $response->assertOk()->assertJsonStructure([
            'active_devices' => ['daily', 'weekly', 'monthly'],
            'total_devices',
            'generated_at',
        ]);
    }

    public function test_it_never_exposes_per_device_or_granular_fields(): void
    {
        Device::factory()->create();

        $response = $this->getJson('/api/v1/stats/public');

        $response->assertOk();
        $keys = array_keys($response->json());
        $this->assertSame(['active_devices', 'total_devices', 'generated_at'], $keys);
    }

    public function test_it_is_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->getJson('/api/v1/stats/public')->assertOk();
        }

        $this->getJson('/api/v1/stats/public')->assertStatus(429);
    }
}
