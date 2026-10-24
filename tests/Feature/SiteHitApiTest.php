<?php

namespace Tests\Feature;

use App\Jobs\ProcessSiteHitBatchJob;
use App\Models\WebBotHitSummary;
use App\Models\WebClick;
use App\Models\WebError;
use App\Models\WebEvent;
use App\Models\WebPageview;
use App\Models\WebSession;
use App\Models\WebVisitor;
use App\Services\WebAnalytics\SiteHitProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Torann\GeoIP\Facades\GeoIP;
use Torann\GeoIP\Location;

class SiteHitApiTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private const SITE = 'https://rezure.redscale.my.id';

    private string $visitorId;

    private string $sessionId;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-24 08:00:00');
        config(['web-analytics.allowed_hosts' => ['rezure.redscale.my.id']]);

        $this->visitorId = (string) Str::uuid();
        $this->sessionId = (string) Str::uuid();
    }

    public function test_a_landing_pageview_opens_a_visitor_and_a_session_with_its_acquisition(): void
    {
        GeoIP::shouldReceive('getLocation')->once()->andReturn(new Location([
            'iso_code' => 'id', 'state_name' => 'Jakarta', 'city' => 'Jakarta', 'postal_code' => '10110',
            'lat' => -6.2, 'lon' => 106.8, 'timezone' => 'Asia/Jakarta', 'default' => false,
        ]));

        $pageviewId = (string) Str::uuid();

        $this->sendBatch([
            $this->pageviewHit($pageviewId, self::SITE.'/guide/installation/?utm_source=reddit&utm_medium=social&utm_campaign=launch', 'https://www.reddit.com/r/laravel/'),
        ], server: ['REMOTE_ADDR' => '203.0.113.10'])->assertStatus(202)->assertJson(['status' => 'queued']);

        $session = WebSession::sole();

        $this->assertSame($this->sessionId, $session->session_uuid);
        $this->assertSame('/guide/installation', $session->entry_path);
        $this->assertSame('Developer Communities', $session->channel);
        $this->assertSame('Reddit', $session->source);
        $this->assertSame('reddit.com', $session->referrer_host);
        $this->assertSame('launch', $session->utm_campaign);
        $this->assertSame('203.0.113.10', $session->ip_address);
        $this->assertSame('ID', $session->country_code);
        $this->assertSame('Jakarta', $session->city);
        $this->assertSame('Chrome', $session->browser);
        $this->assertSame('Windows', $session->os);
        $this->assertSame('desktop', $session->device_type);
        $this->assertSame('wide', $session->viewport_bucket);
        $this->assertTrue($session->is_bounce);

        $visitor = WebVisitor::sole();
        $this->assertSame($this->visitorId, $visitor->visitor_uuid);
        $this->assertSame('Developer Communities', $visitor->first_channel);
        $this->assertSame(1, $visitor->sessions_count);
        $this->assertSame(1, $visitor->pageviews_count);

        $pageview = WebPageview::sole();
        $this->assertSame($pageviewId, $pageview->pageview_uuid);
        $this->assertTrue($pageview->is_entry);
        $this->assertTrue($pageview->is_exit);
    }

    public function test_a_text_plain_body_from_send_beacon_is_accepted(): void
    {
        $body = json_encode($this->batch([$this->pageviewHit((string) Str::uuid(), self::SITE.'/')]));

        $this->call('POST', '/api/v1/site/hit', [], [], [], [
            'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
            'HTTP_USER_AGENT' => self::CHROME_UA,
        ], $body)->assertStatus(202);

        $this->assertDatabaseCount('web_pageviews', 1);
    }

    public function test_the_batch_is_queued_on_the_web_queue(): void
    {
        Queue::fake();

        $this->sendBatch([$this->pageviewHit((string) Str::uuid(), self::SITE.'/')])->assertStatus(202);

        Queue::assertPushedOn('web', ProcessSiteHitBatchJob::class);
        $this->assertDatabaseCount('web_pageviews', 0);
    }

    public function test_sending_the_same_batch_twice_changes_nothing_the_second_time(): void
    {
        $pageviewId = (string) Str::uuid();
        $hits = [
            $this->pageviewHit($pageviewId, self::SITE.'/'),
            $this->eventHit($pageviewId, 'outbound_click', 'https://github.com/myahyazahid/rezure'),
        ];

        $this->sendBatch($hits)->assertStatus(202);
        $this->sendBatch($hits)->assertStatus(202);

        $this->assertDatabaseCount('web_visitors', 1);
        $this->assertDatabaseCount('web_sessions', 1);
        $this->assertDatabaseCount('web_pageviews', 1);
        $this->assertDatabaseCount('web_events', 1);

        $session = WebSession::sole();
        $this->assertSame(1, $session->pageviews_count);
        $this->assertSame(1, $session->events_count);
        $this->assertSame(1, WebVisitor::sole()->sessions_count);
    }

    public function test_engagement_keeps_the_maximum_and_marks_the_session_engaged(): void
    {
        $pageviewId = (string) Str::uuid();

        $this->sendBatch([$this->pageviewHit($pageviewId, self::SITE.'/download')]);
        $this->sendBatch([$this->engagementHit($pageviewId, 15000, 80, [25, 50, 75])]);
        $this->sendBatch([$this->engagementHit($pageviewId, 4000, 30, [25])]);

        $pageview = WebPageview::sole();
        $this->assertSame(15000, $pageview->engaged_ms);
        $this->assertSame(80, $pageview->max_scroll_pct);
        $this->assertSame([25, 50, 75], $pageview->scroll_milestones);

        $session = WebSession::sole();
        $this->assertSame(15, $session->engaged_seconds);
        $this->assertSame(15, $session->duration_seconds);
        $this->assertTrue($session->is_engaged);
        $this->assertFalse($session->is_bounce);
    }

    public function test_two_pageviews_make_an_engaged_session_with_entry_and_exit(): void
    {
        $first = (string) Str::uuid();
        $second = (string) Str::uuid();

        $this->sendBatch([
            $this->pageviewHit($first, self::SITE.'/', at: now()->subSeconds(40)),
            $this->pageviewHit($second, self::SITE.'/download', self::SITE.'/', at: now()->subSeconds(10)),
        ]);

        $session = WebSession::sole();
        $this->assertSame(2, $session->pageviews_count);
        $this->assertSame('/', $session->entry_path);
        $this->assertSame('/download', $session->exit_path);
        $this->assertFalse($session->is_bounce);
        $this->assertSame(30, $session->duration_seconds);

        $this->assertSame([1, 2], WebPageview::orderBy('sequence')->pluck('sequence')->all());
        $this->assertTrue(WebPageview::where('pageview_uuid', $second)->value('is_exit'));
        $this->assertFalse((bool) WebPageview::where('pageview_uuid', $first)->value('is_exit'));
    }

    public function test_a_landing_pageview_processed_after_a_later_one_still_decides_the_acquisition(): void
    {
        $landing = (string) Str::uuid();
        $later = (string) Str::uuid();

        $this->sendBatch([$this->pageviewHit($later, self::SITE.'/download', self::SITE.'/', at: now()->subSeconds(10))]);
        $this->assertSame('Direct', WebSession::sole()->channel);

        $this->sendBatch([$this->pageviewHit($landing, self::SITE.'/', 'https://www.google.com/', at: now()->subSeconds(60))]);

        $session = WebSession::sole();
        $this->assertSame('Organic Search', $session->channel);
        $this->assertSame('Google', $session->source);
        $this->assertSame('/', $session->entry_path);
        $this->assertSame('Organic Search', WebVisitor::sole()->first_channel);
        $this->assertSame(1, WebPageview::where('pageview_uuid', $landing)->value('sequence'));
    }

    public function test_events_clicks_errors_and_vitals_are_stored_against_their_pageview(): void
    {
        $pageviewId = (string) Str::uuid();

        $this->sendBatch([
            $this->pageviewHit($pageviewId, self::SITE.'/guide/installation'),
            $this->eventHit($pageviewId, 'docs_search', 'php version', ['results' => 3]),
            $this->hit('click', $pageviewId, [
                'x_pct' => 42.5, 'y_px' => 1310, 'page_height' => 4200, 'viewport_width' => 390,
                'selector' => 'a.download-btn', 'tag' => 'a', 'text' => 'Download', 'is_rage' => true,
            ]),
            $this->hit('error', $pageviewId, ['message' => 'TypeError: x is undefined', 'source' => self::SITE.'/assets/app.js', 'line' => 12, 'column' => 8]),
            $this->hit('vitals', $pageviewId, ['lcp_ms' => 1840, 'inp_ms' => 96, 'cls' => 0.02, 'ttfb_ms' => 210]),
        ])->assertStatus(202);

        $event = WebEvent::sole();
        $this->assertSame('docs_search', $event->name);
        $this->assertSame('php version', $event->label);
        $this->assertSame(['results' => 3], $event->props);
        $this->assertSame('/guide/installation', $event->path);

        $click = WebClick::sole();
        $this->assertSame('mobile', $click->viewport_bucket);
        $this->assertTrue($click->is_rage);
        $this->assertSame('/guide/installation', $click->path);

        $error = WebError::sole();
        $this->assertSame(WebError::fingerprintFor('TypeError: x is undefined', self::SITE.'/assets/app.js', 12), $error->fingerprint);
        $this->assertSame('Chrome', $error->browser);

        $pageview = WebPageview::sole();
        $this->assertSame(1840, $pageview->lcp_ms);
        $this->assertSame(0.02, $pageview->cls);
    }

    public function test_a_client_reported_download_event_is_ignored(): void
    {
        $pageviewId = (string) Str::uuid();

        $this->sendBatch([
            $this->pageviewHit($pageviewId, self::SITE.'/download'),
            $this->eventHit($pageviewId, WebEvent::DOWNLOAD, '3.1.0'),
        ]);

        $this->assertDatabaseCount('web_events', 0);
        $this->assertFalse(WebSession::sole()->has_conversion);
    }

    public function test_a_batch_from_another_host_is_dropped(): void
    {
        $this->sendBatch([$this->pageviewHit((string) Str::uuid(), 'https://evil.example/')])->assertStatus(202);

        $this->assertDatabaseCount('web_sessions', 0);
    }

    public function test_a_batch_with_a_foreign_origin_header_is_dropped(): void
    {
        $this->sendBatch([$this->pageviewHit((string) Str::uuid(), self::SITE.'/')], ['Origin' => 'https://evil.example'])->assertStatus(202);

        $this->assertDatabaseCount('web_sessions', 0);
    }

    public function test_bot_traffic_is_only_counted(): void
    {
        $this->sendBatch(
            [$this->pageviewHit((string) Str::uuid(), self::SITE.'/')],
            ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'],
        )->assertStatus(202);

        $this->assertDatabaseCount('web_sessions', 0);

        $summary = WebBotHitSummary::sole();
        $this->assertSame('Googlebot', $summary->bot_name);
        $this->assertSame(1, $summary->hits);
        $this->assertSame('2026-10-24', $summary->date->toDateString());
    }

    public function test_client_clock_skew_is_corrected(): void
    {
        // The browser's clock runs an hour slow: it stamps both the hit and
        // the batch an hour in the past.
        $this->sendBatch(
            [$this->pageviewHit((string) Str::uuid(), self::SITE.'/', at: now()->subHour()->subSeconds(5))],
            sentAt: now()->subHour(),
        );

        $this->assertSame(now()->subSeconds(5)->toDateTimeString(), WebPageview::sole()->occurred_at->toDateTimeString());
    }

    public function test_hits_older_than_the_maximum_age_are_dropped(): void
    {
        $this->sendBatch([$this->pageviewHit((string) Str::uuid(), self::SITE.'/', at: now()->subDays(8))]);

        $this->assertDatabaseCount('web_pageviews', 0);
    }

    public function test_hits_whose_pageview_is_not_stored_yet_are_requeued(): void
    {
        $pageviewId = (string) Str::uuid();
        $batch = $this->batch([$this->engagementHit($pageviewId, 5000, 40, [25])]);

        Queue::fake();
        (new ProcessSiteHitBatchJob($batch, $this->requestContext()))->handle(app(SiteHitProcessor::class));

        Queue::assertPushedOn('web', ProcessSiteHitBatchJob::class);
        $this->assertDatabaseCount('web_pageviews', 0);
    }

    public function test_requeued_hits_are_stored_once_their_pageview_exists(): void
    {
        $pageviewId = (string) Str::uuid();

        $this->sendBatch([$this->pageviewHit($pageviewId, self::SITE.'/')]);

        $retry = $this->batch([$this->engagementHit($pageviewId, 12000, 90, [25, 50, 75, 90])]);
        (new ProcessSiteHitBatchJob($retry, $this->requestContext(), 2))->handle(app(SiteHitProcessor::class));

        $this->assertSame(12000, WebPageview::sole()->engaged_ms);
    }

    public function test_an_invalid_batch_is_rejected(): void
    {
        $this->postJson('/api/v1/site/hit', [
            'v' => 1,
            'visitor_id' => 'not-a-uuid',
            'session_id' => $this->sessionId,
            'sent_at' => now()->toIso8601String(),
            'context' => ['is_new_visitor' => true, 'visit_number' => 1],
            'hits' => [['type' => 'teleport', 'id' => (string) Str::uuid(), 'pageview_id' => (string) Str::uuid(), 'at' => now()->toIso8601String()]],
        ])->assertStatus(422)->assertJsonValidationErrors(['visitor_id', 'hits.0.type']);
    }

    public function test_a_pageview_hit_requires_a_url(): void
    {
        $hit = $this->pageviewHit((string) Str::uuid(), self::SITE.'/');
        unset($hit['url']);

        $this->sendBatch([$hit])->assertStatus(422)->assertJsonValidationErrors(['hits.0.url']);
    }

    public function test_a_batch_of_more_than_fifty_hits_is_rejected(): void
    {
        $pageviewId = (string) Str::uuid();
        $hits = array_map(fn (): array => $this->engagementHit($pageviewId, 1000, 10, []), range(1, 51));

        $this->sendBatch($hits)->assertStatus(422)->assertJsonValidationErrors(['hits']);
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @param  array<string, string>  $headers
     * @param  array<string, string>  $server
     */
    private function sendBatch(array $hits, array $headers = [], array $server = [], ?Carbon $sentAt = null): TestResponse
    {
        return $this->postJson(
            '/api/v1/site/hit',
            $this->batch($hits, $sentAt),
            ['User-Agent' => self::CHROME_UA, ...$headers, ...$server],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @return array<string, mixed>
     */
    private function batch(array $hits, ?Carbon $sentAt = null): array
    {
        return [
            'v' => 1,
            'visitor_id' => $this->visitorId,
            'session_id' => $this->sessionId,
            'sent_at' => ($sentAt ?? now())->toIso8601String(),
            'context' => [
                'is_new_visitor' => true,
                'visit_number' => 1,
                'screen_width' => 1920,
                'screen_height' => 1080,
                'viewport_width' => 1440,
                'viewport_height' => 900,
                'pixel_ratio' => 1.5,
                'language' => 'id-ID',
                'languages' => ['id-ID', 'en-US'],
                'timezone' => 'Asia/Jakarta',
                'color_scheme' => 'dark',
                'site_theme' => 'dark',
            ],
            'hits' => $hits,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function pageviewHit(string $pageviewId, string $url, ?string $referrer = null, ?Carbon $at = null): array
    {
        return $this->hit('pageview', $pageviewId, ['url' => $url, 'title' => 'Rezure', 'referrer' => $referrer, 'not_found' => false], $at);
    }

    /**
     * @param  list<int>  $milestones
     * @return array<string, mixed>
     */
    private function engagementHit(string $pageviewId, int $engagedMs, int $scroll, array $milestones): array
    {
        return $this->hit('engagement', $pageviewId, ['engaged_ms' => $engagedMs, 'max_scroll_pct' => $scroll, 'scroll_milestones' => $milestones]);
    }

    /**
     * @param  array<string, mixed>|null  $props
     * @return array<string, mixed>
     */
    private function eventHit(string $pageviewId, string $name, ?string $label = null, ?array $props = null): array
    {
        return $this->hit('event', $pageviewId, ['name' => $name, 'label' => $label, 'props' => $props]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function hit(string $type, string $pageviewId, array $fields, ?Carbon $at = null): array
    {
        return [
            'type' => $type,
            'id' => (string) Str::uuid(),
            'pageview_id' => $pageviewId,
            'at' => ($at ?? now())->toIso8601String(),
            ...$fields,
        ];
    }

    /**
     * @return array{ip: ?string, user_agent: ?string, accept_language: ?string, origin: ?string, received_at: string}
     */
    private function requestContext(): array
    {
        return [
            'ip' => '127.0.0.1',
            'user_agent' => self::CHROME_UA,
            'accept_language' => 'id-ID',
            'origin' => null,
            'received_at' => now()->toIso8601String(),
        ];
    }
}
