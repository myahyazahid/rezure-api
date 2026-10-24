<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\GithubReleaseDownload;
use App\Models\WebEvent;
use App\Models\WebSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebConversionCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-24 08:00:00');
    }

    public function test_an_install_is_linked_to_the_visitor_who_downloaded_from_the_same_ip(): void
    {
        $session = WebSession::factory()->create();
        WebEvent::factory()->download()->create([
            'web_session_id' => $session->id,
            'ip_address' => '203.0.113.10',
            'occurred_at' => now()->subHours(5),
        ]);

        $device = Device::factory()->create(['first_seen_ip' => '203.0.113.10', 'first_seen_at' => now()->subHours(4)]);
        $stranger = Device::factory()->create(['first_seen_ip' => '198.51.100.1', 'first_seen_at' => now()->subHours(4)]);

        $this->artisan('app:attribute-web-installs')->assertSuccessful();

        $this->assertSame($device->id, $session->visitor->fresh()->device_id);
        $this->assertDatabaseMissing('web_visitors', ['device_id' => $stranger->id]);
    }

    public function test_a_download_outside_the_attribution_window_is_not_linked(): void
    {
        $session = WebSession::factory()->create();
        WebEvent::factory()->download()->create([
            'web_session_id' => $session->id,
            'ip_address' => '203.0.113.10',
            'occurred_at' => now()->subDays(5),
        ]);

        Device::factory()->create(['first_seen_ip' => '203.0.113.10', 'first_seen_at' => now()->subHour()]);

        $this->artisan('app:attribute-web-installs')->assertSuccessful();

        $this->assertNull($session->visitor->fresh()->device_id);
    }

    public function test_github_asset_download_counts_are_snapshotted_once_per_day(): void
    {
        Http::fake([
            'api.github.com/repos/*/releases*' => Http::response([
                ['tag_name' => 'v3.1.0', 'assets' => [['name' => 'Rezure_3.1.0_x64-setup.exe', 'download_count' => 120]]],
                ['tag_name' => 'v3.0.2', 'assets' => [['name' => 'Rezure_3.0.2_x64-setup.exe', 'download_count' => 300]]],
            ]),
        ]);

        $this->artisan('app:sync-github-download-counts')->assertSuccessful();
        $this->artisan('app:sync-github-download-counts')->assertSuccessful();

        $this->assertSame(2, GithubReleaseDownload::count());
        $this->assertDatabaseHas('github_release_downloads', ['version' => '3.1.0', 'download_count' => 120]);
    }

    public function test_a_failed_github_request_fails_the_command_without_writing(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 500)]);

        $this->artisan('app:sync-github-download-counts')->assertFailed();

        $this->assertSame(0, GithubReleaseDownload::count());
    }
}
