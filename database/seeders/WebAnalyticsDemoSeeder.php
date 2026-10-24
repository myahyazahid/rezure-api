<?php

namespace Database\Seeders;

use App\Models\WebError;
use App\Models\WebEvent;
use App\Models\WebFunnel;
use App\Models\WebGoal;
use App\Support\ViewportBucket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills the web_* tables with ~90 days of plausible website traffic so the
 * Website dashboard can be developed and its queries EXPLAINed before real
 * visits pile up (v4, Fase 4.3). Like TelemetryDemoSeeder it's never part
 * of DatabaseSeeder::run() — run it explicitly:
 *
 *     php artisan db:seed --class=WebAnalyticsDemoSeeder
 *
 * Rows are bulk-inserted with explicit ids (continuing from the current
 * max) so a large run stays fast, then the daily summaries are rolled up.
 */
class WebAnalyticsDemoSeeder extends Seeder
{
    private const DAYS = 90;

    private const VISITORS = 3000;

    /**
     * [referrer, utm_source, channel, source, weight]
     *
     * @var list<array{0: ?string, 1: ?string, 2: string, 3: string, 4: int}>
     */
    private const SOURCES = [
        [null, null, 'Direct', 'Direct', 30],
        ['https://www.google.com/', null, 'Organic Search', 'Google', 28],
        ['https://www.bing.com/', null, 'Organic Search', 'Bing', 3],
        ['https://github.com/myahyazahid/rezure', null, 'Developer Communities', 'GitHub', 12],
        ['https://www.reddit.com/r/laravel/', 'reddit', 'Developer Communities', 'Reddit', 8],
        ['https://news.ycombinator.com/', null, 'Developer Communities', 'Hacker News', 2],
        ['https://chatgpt.com/', null, 'AI Assistants', 'ChatGPT', 5],
        ['https://www.perplexity.ai/', null, 'AI Assistants', 'Perplexity', 2],
        ['https://t.co/abc', null, 'Social', 'X', 4],
        ['https://www.youtube.com/', null, 'Social', 'YouTube', 3],
        ['https://laravel-news.com/', null, 'Developer Communities', 'Laravel News', 2],
        [null, 'newsletter', 'Email', 'newsletter', 1],
    ];

    /**
     * [country, region, city, lat, lon, weight]
     *
     * @var list<array{0: string, 1: string, 2: string, 3: float, 4: float, 5: int}>
     */
    private const PLACES = [
        ['ID', 'Jakarta', 'Jakarta', -6.2, 106.82, 22],
        ['ID', 'West Java', 'Bandung', -6.91, 107.61, 10],
        ['ID', 'East Java', 'Surabaya', -7.25, 112.75, 7],
        ['ID', 'Yogyakarta', 'Yogyakarta', -7.8, 110.36, 5],
        ['MY', 'Kuala Lumpur', 'Kuala Lumpur', 3.14, 101.69, 6],
        ['IN', 'Maharashtra', 'Mumbai', 19.07, 72.87, 8],
        ['US', 'California', 'San Francisco', 37.77, -122.42, 8],
        ['DE', 'Berlin', 'Berlin', 52.52, 13.4, 5],
        ['GB', 'England', 'London', 51.51, -0.13, 4],
        ['BR', 'São Paulo', 'São Paulo', -23.55, -46.63, 4],
        ['PH', 'Metro Manila', 'Manila', 14.6, 120.98, 4],
        ['VN', 'Ho Chi Minh', 'Ho Chi Minh City', 10.82, 106.63, 3],
        ['NG', 'Lagos', 'Lagos', 6.52, 3.38, 2],
    ];

    /**
     * [browser, version, engine, os, os_version, device_type, brand, viewport_width, weight]
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: ?string, 7: int, 8: int}>
     */
    private const AGENTS = [
        ['Chrome', '120.0', 'Blink', 'Windows', '10', 'desktop', null, 1536, 40],
        ['Chrome', '121.0', 'Blink', 'Windows', '11', 'desktop', null, 1920, 12],
        ['Microsoft Edge', '120.0', 'Blink', 'Windows', '10', 'desktop', null, 1366, 12],
        ['Firefox', '122.0', 'Gecko', 'Windows', '10', 'desktop', null, 1440, 8],
        ['Chrome', '120.0', 'Blink', 'Mac', '14.2', 'desktop', 'Apple', 1440, 5],
        ['Chrome Mobile', '120.0', 'Blink', 'Android', '14', 'smartphone', 'Samsung', 412, 12],
        ['Mobile Safari', '17.1', 'WebKit', 'iOS', '17.1', 'smartphone', 'Apple', 390, 7],
        ['Chrome', '120.0', 'Blink', 'GNU/Linux', '', 'desktop', null, 1920, 4],
    ];

    /**
     * Paths a visit wanders through, with how likely each is to come next.
     *
     * @var array<string, list<string>>
     */
    private const NEXT_PAGES = [
        '/' => ['/download', '/download', '/guide', '/guide/installation', '/blog'],
        '/download' => ['/guide/installation', '/guide/first-run', '/'],
        '/guide' => ['/guide/installation', '/guide/first-run', '/guide/faq'],
        '/guide/installation' => ['/guide/first-run', '/download', '/guide/project-php-version'],
        '/guide/first-run' => ['/guide/sharing', '/guide/remote-databases', '/guide/faq'],
        '/guide/faq' => ['/download', '/guide/installation'],
        '/guide/sharing' => ['/guide/remote-databases', '/download'],
        '/guide/remote-databases' => ['/guide/faq'],
        '/guide/project-php-version' => ['/guide/first-run'],
        '/blog' => ['/blog/posts/whats-new-in-rezure-3-1', '/blog/posts/local-https'],
        '/blog/posts/whats-new-in-rezure-3-1' => ['/download', '/blog'],
        '/blog/posts/local-https' => ['/guide', '/blog'],
    ];

    private const LANDING_PAGES = ['/', '/', '/', '/download', '/guide/installation', '/guide/installation', '/guide/faq', '/blog/posts/whats-new-in-rezure-3-1', '/guide/remote-databases', '/missing-page'];

    private int $visitorId;

    private int $sessionId;

    private int $pageviewId;

    /** @var array<string, list<array<string, mixed>>> */
    private array $rows = ['web_visitors' => [], 'web_sessions' => [], 'web_pageviews' => [], 'web_events' => [], 'web_clicks' => [], 'web_errors' => []];

    public function run(): void
    {
        $this->visitorId = (int) DB::table('web_visitors')->max('id');
        $this->sessionId = (int) DB::table('web_sessions')->max('id');
        $this->pageviewId = (int) DB::table('web_pageviews')->max('id');

        $now = now();
        $this->command?->getOutput()->writeln('Seeding website visitors, sessions and pageviews...');

        for ($index = 0; $index < self::VISITORS; $index++) {
            $this->seedVisitor($now);

            if (count($this->rows['web_pageviews']) > 2000) {
                $this->flush();
            }
        }

        $this->flush();
        $this->seedBots($now);
        $this->seedGoalsAndFunnel();

        $this->command?->getOutput()->writeln('Rolling up daily summaries...');
        Artisan::call('app:generate-web-summaries', [
            '--from' => $now->copy()->setTimezone(config('app.display_timezone'))->subDays(self::DAYS + 1)->toDateString(),
            '--to' => $now->copy()->setTimezone(config('app.display_timezone'))->subDay()->toDateString(),
        ]);
    }

    private function seedVisitor(Carbon $now): void
    {
        // Traffic grows over the period, so later days get more first visits.
        $firstSeen = $now->copy()->subSeconds((int) (self::DAYS * 86400 * (1 - sqrt(mt_rand() / mt_getrandmax()))));
        $place = $this->weighted(self::PLACES, 5);
        $agent = $this->weighted(self::AGENTS, 8);
        $visits = mt_rand(1, 100) <= 70 ? 1 : mt_rand(2, 6);
        $visitorId = ++$this->visitorId;
        $ip = '203.0.'.mt_rand(0, 255).'.'.mt_rand(1, 254);
        $at = $firstSeen->copy();
        $firstSource = null;
        $totalPageviews = 0;
        $firstDownload = null;
        $lastSeen = $at->copy();

        for ($visit = 1; $visit <= $visits && $at->lt($now); $visit++) {
            $source = $this->weighted(self::SOURCES, 4);
            $firstSource ??= $source;
            [$pageviews, $endedAt, $downloaded] = $this->seedSession($visitorId, $visit, $at, $source, $place, $agent, $ip, $now);
            $totalPageviews += $pageviews;
            $firstDownload ??= $downloaded;
            $lastSeen = $endedAt;
            $at = $at->copy()->addHours(mt_rand(6, 24 * 20));
        }

        $this->rows['web_visitors'][] = [
            'id' => $visitorId,
            'visitor_uuid' => (string) Str::uuid(),
            'first_seen_at' => $firstSeen,
            'last_seen_at' => $lastSeen,
            'sessions_count' => min($visits, $visit - 1),
            'pageviews_count' => $totalPageviews,
            'first_landing_path' => '/',
            'first_referrer_host' => $firstSource[0] !== null ? preg_replace('/^www\./', '', parse_url($firstSource[0], PHP_URL_HOST)) : null,
            'first_channel' => $firstSource[2],
            'first_source' => $firstSource[3],
            'first_utm_source' => $firstSource[1],
            'last_ip_address' => $ip,
            'last_country_code' => $place[0],
            'last_city' => $place[2],
            'last_browser' => $agent[0],
            'last_os' => $agent[3],
            'last_device_type' => $agent[5],
            'first_download_at' => $firstDownload,
            'created_at' => $firstSeen,
            'updated_at' => $lastSeen,
        ];
    }

    /**
     * @param  array<int, mixed>  $source
     * @param  array<int, mixed>  $place
     * @param  array<int, mixed>  $agent
     * @return array{0: int, 1: Carbon, 2: ?Carbon}
     */
    private function seedSession(int $visitorId, int $visit, Carbon $startedAt, array $source, array $place, array $agent, string $ip, Carbon $now): array
    {
        $sessionId = ++$this->sessionId;
        $path = self::LANDING_PAGES[array_rand(self::LANDING_PAGES)];
        $count = mt_rand(1, 100) <= 45 ? 1 : mt_rand(2, 6);
        $at = $startedAt->copy();
        $engagedTotal = 0;
        $paths = [];
        $downloadedAt = null;

        for ($sequence = 1; $sequence <= $count && $at->lt($now); $sequence++) {
            $pageviewId = ++$this->pageviewId;
            $engaged = $count === 1 && mt_rand(1, 100) <= 55 ? mt_rand(500, 9000) : mt_rand(4000, 180000);
            $isMobile = $agent[5] !== 'desktop';

            $this->rows['web_pageviews'][] = [
                'id' => $pageviewId,
                'pageview_uuid' => (string) Str::uuid(),
                'web_session_id' => $sessionId,
                'web_visitor_id' => $visitorId,
                'url' => 'https://rezure.redscale.my.id'.$path.($sequence === 1 && $source[1] ? '?utm_source='.$source[1] : ''),
                'path' => $path,
                'title' => 'Rezure',
                'referrer_url' => $sequence === 1 ? $source[0] : 'https://rezure.redscale.my.id'.end($paths),
                'sequence' => $sequence,
                'occurred_at' => $at->copy(),
                'engaged_ms' => $engaged,
                'max_scroll_pct' => mt_rand(10, 100),
                'is_entry' => $sequence === 1,
                'is_exit' => $sequence === $count,
                'is_not_found' => $path === '/missing-page',
                'lcp_ms' => $sequence === 1 ? (int) ($isMobile ? mt_rand(1400, 5200) : mt_rand(700, 3200)) : null,
                'inp_ms' => $sequence === 1 ? mt_rand(40, 450) : null,
                'cls' => $sequence === 1 ? round(mt_rand(0, 300) / 1000, 3) : null,
                'fcp_ms' => $sequence === 1 ? mt_rand(400, 2600) : null,
                'ttfb_ms' => $sequence === 1 ? mt_rand(80, 1200) : null,
                'page_load_ms' => $sequence === 1 ? mt_rand(900, 5000) : null,
                'created_at' => $at->copy(),
                'updated_at' => $at->copy(),
            ];

            $this->seedInteractions($sessionId, $visitorId, $pageviewId, $path, $at, $agent[7]);

            if ($path === '/download' && $downloadedAt === null && mt_rand(1, 100) <= 45) {
                $downloadedAt = $at->copy()->addSeconds(mt_rand(5, 60));
                $this->event($sessionId, $visitorId, $pageviewId, WebEvent::DOWNLOAD, '3.1.0', $path, $downloadedAt, $ip);
            }

            $paths[] = $path;
            $engagedTotal += $engaged;
            $at->addMilliseconds($engaged + mt_rand(1000, 20000));
            $path = self::NEXT_PAGES[$path][array_rand(self::NEXT_PAGES[$path] ?? ['/'])] ?? '/';
        }

        $pageviews = count($paths);
        $endedAt = $at->copy();
        $isEngaged = $engagedTotal >= 10000 || $pageviews >= 2 || $downloadedAt !== null;

        $this->rows['web_sessions'][] = [
            'id' => $sessionId,
            'session_uuid' => (string) Str::uuid(),
            'web_visitor_id' => $visitorId,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => (int) $startedAt->diffInSeconds($endedAt),
            'engaged_seconds' => intdiv($engagedTotal, 1000),
            'pageviews_count' => $pageviews,
            'events_count' => 0,
            'is_engaged' => $isEngaged,
            'is_bounce' => ! $isEngaged,
            'has_conversion' => $downloadedAt !== null,
            'is_new_visitor' => $visit === 1,
            'visit_number' => $visit,
            'entry_path' => $paths[0] ?? '/',
            'exit_path' => end($paths) ?: '/',
            'referrer_url' => $source[0],
            'referrer_host' => $source[0] !== null ? preg_replace('/^www\./', '', parse_url($source[0], PHP_URL_HOST)) : null,
            'channel' => $source[2],
            'source' => $source[3],
            'utm_source' => $source[1],
            'utm_medium' => $source[1] === 'newsletter' ? 'email' : null,
            'utm_campaign' => $source[1] !== null ? 'launch-3-1' : null,
            'ip_address' => $ip,
            'country_code' => $place[0],
            'region' => $place[1],
            'city' => $place[2],
            'latitude' => $place[3],
            'longitude' => $place[4],
            'browser' => $agent[0],
            'browser_version' => $agent[1],
            'browser_engine' => $agent[2],
            'os' => $agent[3],
            'os_version' => $agent[4] ?: null,
            'device_type' => $agent[5],
            'device_brand' => $agent[6],
            'screen_width' => $agent[7] > 1000 ? 1920 : $agent[7],
            'screen_height' => $agent[7] > 1000 ? 1080 : 844,
            'viewport_width' => $agent[7],
            'viewport_height' => $agent[7] > 1000 ? 900 : 760,
            'viewport_bucket' => ViewportBucket::for($agent[7]),
            'pixel_ratio' => $agent[7] > 1000 ? 1.25 : 3,
            'language' => $place[0] === 'ID' ? 'id-ID' : 'en-US',
            'timezone' => $place[0] === 'ID' ? 'Asia/Jakarta' : 'UTC',
            'color_scheme' => mt_rand(0, 1) ? 'dark' : 'light',
            'site_theme' => mt_rand(0, 3) ? 'dark' : 'light',
            'connection' => '4g',
            'cpu_cores' => [4, 8, 8, 12, 16][array_rand([4, 8, 8, 12, 16])],
            'memory_gb' => 8,
            'created_at' => $startedAt,
            'updated_at' => $endedAt,
        ];

        return [$pageviews, $endedAt, $downloadedAt];
    }

    private function seedInteractions(int $sessionId, int $visitorId, int $pageviewId, string $path, Carbon $at, int $viewportWidth): void
    {
        for ($click = mt_rand(0, 4); $click > 0; $click--) {
            $this->rows['web_clicks'][] = [
                'click_uuid' => (string) Str::uuid(),
                'web_pageview_id' => $pageviewId,
                'web_session_id' => $sessionId,
                'path' => $path,
                'x_pct' => min(100, max(0, round(50 + (mt_rand(-400, 400) / 10), 2))),
                'y_px' => mt_rand(100, 2600),
                'page_height' => 3200,
                'viewport_width' => $viewportWidth,
                'viewport_bucket' => ViewportBucket::for($viewportWidth),
                'selector' => ['a.VPButton.brand', 'a.VPLink', 'button.copy', 'div.vp-doc h2'][mt_rand(0, 3)],
                'tag' => 'a',
                'text' => ['Download for Windows', 'Get started', 'Copy', 'GitHub'][mt_rand(0, 3)],
                'is_rage' => mt_rand(1, 100) <= 3,
                'occurred_at' => $at->copy()->addSeconds(mt_rand(1, 30)),
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        $roll = mt_rand(1, 100);
        $when = $at->copy()->addSeconds(mt_rand(2, 40));

        match (true) {
            $roll <= 8 => $this->event($sessionId, $visitorId, $pageviewId, 'outbound_click', 'https://github.com/myahyazahid/rezure', $path, $when),
            $roll <= 14 && str_starts_with($path, '/guide') => $this->event($sessionId, $visitorId, $pageviewId, 'copy_code', ['bash', 'powershell', 'php'][mt_rand(0, 2)], $path, $when),
            $roll <= 20 && str_starts_with($path, '/guide') => $this->event($sessionId, $visitorId, $pageviewId, 'docs_search', ['php version', 'mysql port', 'ssl', 'cloudflare tunnel', 'composer'][mt_rand(0, 4)], $path, $when),
            $roll <= 26 && str_starts_with($path, '/blog/posts/') => $this->event($sessionId, $visitorId, $pageviewId, 'blog_read_complete', basename($path), $path, $when),
            $roll <= 30 && $path === '/' => $this->event($sessionId, $visitorId, $pageviewId, 'cta_click', 'hero:/download', $path, $when),
            $roll <= 31 => $this->error($sessionId, $pageviewId, $path, $when),
            default => null,
        };
    }

    private function event(int $sessionId, int $visitorId, int $pageviewId, string $name, ?string $label, string $path, Carbon $at, ?string $ip = null): void
    {
        $this->rows['web_events'][] = [
            'event_uuid' => (string) Str::uuid(),
            'web_session_id' => $sessionId,
            'web_visitor_id' => $visitorId,
            'web_pageview_id' => $pageviewId,
            'name' => $name,
            'label' => $label,
            'props' => null,
            'path' => $path,
            'ip_address' => $ip,
            'occurred_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    private function error(int $sessionId, int $pageviewId, string $path, Carbon $at): void
    {
        $message = ['TypeError: Cannot read properties of null (reading \'focus\')', 'ResizeObserver loop completed with undelivered notifications.'][mt_rand(0, 1)];
        $source = 'https://rezure.redscale.my.id/assets/app.js';

        $this->rows['web_errors'][] = [
            'error_uuid' => (string) Str::uuid(),
            'web_session_id' => $sessionId,
            'web_pageview_id' => $pageviewId,
            'fingerprint' => WebError::fingerprintFor($message, $source, 12),
            'message' => $message,
            'source' => $source,
            'line' => 12,
            'column' => 8,
            'stack' => "{$message}\n    at focusSearch (app.js:12:8)\n    at HTMLDocument.<anonymous> (app.js:40:3)",
            'path' => $path,
            'browser' => 'Chrome',
            'browser_version' => '120.0',
            'os' => 'Windows',
            'occurred_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    /**
     * Sessions and visitors before their children (foreign keys).
     */
    private function flush(): void
    {
        foreach (['web_visitors', 'web_sessions', 'web_pageviews', 'web_events', 'web_clicks', 'web_errors'] as $table) {
            foreach (array_chunk($this->rows[$table], 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }

            $this->rows[$table] = [];
        }
    }

    private function seedBots(Carbon $now): void
    {
        $rows = [];

        for ($day = 0; $day < self::DAYS; $day++) {
            foreach (['Googlebot' => 'Search bot', 'BingBot' => 'Search bot', 'GPTBot' => 'Crawler', 'AhrefsBot' => 'Crawler'] as $bot => $category) {
                $rows[] = ['date' => $now->copy()->subDays($day)->toDateString(), 'bot_name' => $bot, 'bot_category' => $category, 'hits' => mt_rand(5, 120), 'created_at' => $now, 'updated_at' => $now];
            }
        }

        DB::table('web_bot_hit_summary')->upsert($rows, ['date', 'bot_name'], ['hits']);
    }

    private function seedGoalsAndFunnel(): void
    {
        WebGoal::firstOrCreate(['name' => 'Download'], ['type' => WebGoal::TYPE_EVENT, 'match' => WebEvent::DOWNLOAD]);
        WebGoal::firstOrCreate(['name' => 'Read the installation guide'], ['type' => WebGoal::TYPE_PAGEVIEW, 'match' => '/guide/installation']);

        WebFunnel::firstOrCreate(['name' => 'Home → download'], [
            'steps' => [
                ['type' => 'pageview', 'match' => '/', 'label' => null],
                ['type' => 'pageview', 'match' => '/download', 'label' => null],
                ['type' => 'event', 'match' => WebEvent::DOWNLOAD, 'label' => null],
            ],
            'scope' => WebFunnel::SCOPE_SESSION,
            'window_hours' => 24,
        ]);
    }

    /**
     * Picks one row, weighted by its `$weightIndex` column.
     *
     * @param  list<array<int, mixed>>  $rows
     * @return array<int, mixed>
     */
    private function weighted(array $rows, int $weightIndex): array
    {
        $roll = mt_rand(1, array_sum(array_column($rows, $weightIndex)));

        foreach ($rows as $row) {
            $roll -= $row[$weightIndex];

            if ($roll <= 0) {
                return $row;
            }
        }

        return $rows[0];
    }
}
