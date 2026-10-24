<?php

namespace Tests\Feature;

use App\Jobs\RecordSiteDownloadJob;
use App\Models\Release;
use App\Models\WebEvent;
use App\Models\WebPageview;
use App\Models\WebSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SiteDownloadTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    public function test_redirects_to_the_newest_installer_and_records_the_download_against_the_session(): void
    {
        Release::factory()->create(['version' => '3.0.2', 'download_url' => 'https://example.com/Rezure_3.0.2.exe']);
        Release::factory()->create(['version' => '3.1.0', 'download_url' => 'https://example.com/Rezure_3.1.0.exe']);

        $session = WebSession::factory()->create();
        $pageview = WebPageview::factory()->create(['web_session_id' => $session->id, 'path' => '/download']);

        $query = http_build_query(['vid' => $session->visitor->visitor_uuid, 'sid' => $session->session_uuid, 'pid' => $pageview->pageview_uuid]);

        $this->get('/api/v1/site/download?'.$query, ['User-Agent' => self::CHROME_UA, 'REMOTE_ADDR' => '203.0.113.10'])
            ->assertRedirect('https://example.com/Rezure_3.1.0.exe');

        $event = WebEvent::sole();
        $this->assertSame(WebEvent::DOWNLOAD, $event->name);
        $this->assertSame('3.1.0', $event->label);
        $this->assertSame($session->id, $event->web_session_id);
        $this->assertSame($pageview->id, $event->web_pageview_id);
        $this->assertSame('/download', $event->path);
        $this->assertSame('203.0.113.10', $event->ip_address);

        $this->assertTrue($session->fresh()->has_conversion);
        $this->assertFalse($session->fresh()->is_bounce);
        $this->assertNotNull($session->visitor->fresh()->first_download_at);
    }

    public function test_a_plain_link_without_tracker_ids_still_counts(): void
    {
        Release::factory()->create(['version' => '3.1.0', 'download_url' => 'https://example.com/Rezure_3.1.0.exe']);

        $this->get('/api/v1/site/download?vid=not-a-uuid', ['User-Agent' => self::CHROME_UA])
            ->assertRedirect('https://example.com/Rezure_3.1.0.exe');

        $event = WebEvent::sole();
        $this->assertNull($event->web_session_id);
        $this->assertNull($event->web_visitor_id);
    }

    public function test_falls_back_to_github_releases_without_a_release_download_url(): void
    {
        $this->get('/api/v1/site/download', ['User-Agent' => self::CHROME_UA])
            ->assertRedirect(config('web-analytics.download_fallback_url'));
    }

    public function test_a_bot_following_the_link_is_not_counted_as_a_download(): void
    {
        $this->get('/api/v1/site/download', ['User-Agent' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'])
            ->assertRedirect();

        $this->assertDatabaseCount('web_events', 0);
        $this->assertDatabaseHas('web_bot_hit_summary', ['bot_name' => 'BingBot']);
    }

    public function test_the_download_is_recorded_on_the_web_queue(): void
    {
        Queue::fake();

        $this->get('/api/v1/site/download')->assertRedirect();

        Queue::assertPushedOn('web', RecordSiteDownloadJob::class);
    }
}
