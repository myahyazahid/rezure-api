<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\User;
use App\Models\WebError;
use App\Models\WebFunnel;
use App\Models\WebGoal;
use App\Models\WebSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardWebsiteTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    private const SITE = 'https://rezure.redscale.my.id';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-24 08:00:00');
        config(['web-analytics.allowed_hosts' => ['rezure.redscale.my.id']]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'overview' => ['/dashboard/website'],
            'realtime' => ['/dashboard/website/realtime'],
            'pages' => ['/dashboard/website/pages'],
            'entry pages' => ['/dashboard/website/pages?tab=entries'],
            'page detail' => ['/dashboard/website/pages/detail?path=/download'],
            'content' => ['/dashboard/website/content'],
            'acquisition' => ['/dashboard/website/acquisition'],
            'audience' => ['/dashboard/website/audience'],
            'visitors' => ['/dashboard/website/visitors'],
            'technology' => ['/dashboard/website/technology'],
            'behavior' => ['/dashboard/website/behavior'],
            'heatmap' => ['/dashboard/website/heatmap?path=/download&viewport=wide'],
            'events' => ['/dashboard/website/events'],
            'goals' => ['/dashboard/website/goals'],
            'funnels' => ['/dashboard/website/funnels'],
            'performance' => ['/dashboard/website/performance'],
            'errors' => ['/dashboard/website/errors'],
            'conversion' => ['/dashboard/website/conversion'],
            'filtered overview' => ['/dashboard/website?period=7d&compare=year&f[channel]=Developer%20Communities&f[page]=/guide/*'],
            'custom range' => ['/dashboard/website/acquisition?period=custom&from=2026-10-01&to=2026-10-24&interval=week'],
        ];
    }

    #[DataProvider('pages')]
    public function test_every_website_page_requires_login(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    #[DataProvider('pages')]
    public function test_every_website_page_renders_without_data(string $url): void
    {
        $this->actingAs(User::factory()->create())->get($url)->assertOk();
    }

    #[DataProvider('pages')]
    public function test_every_website_page_renders_with_data(string $url): void
    {
        $this->seedTraffic();
        WebGoal::factory()->create();

        $this->actingAs(User::factory()->create())->get($url)->assertOk();
    }

    public function test_the_overview_shows_the_period_numbers_and_top_lists(): void
    {
        $this->seedTraffic();

        $this->actingAs(User::factory()->create())
            ->get('/dashboard/website?period=7d')
            ->assertOk()
            ->assertSee('Unique visitors')
            ->assertSee('/guide/installation')
            ->assertSee('Developer Communities')
            ->assertSee('Reddit')
            ->assertSee('outbound_click');
    }

    public function test_the_sidebar_links_to_the_website_section(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee(route('dashboard.website.overview'));
    }

    public function test_drill_down_links_carry_the_filter(): void
    {
        $this->seedTraffic();

        $this->actingAs(User::factory()->create())
            ->get('/dashboard/website?period=7d')
            ->assertSee('f%5Bchannel%5D=Developer%20Communities', false);
    }

    public function test_an_active_filter_shows_as_a_removable_chip(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard/website?f[country]=ID')
            ->assertSee('Filtered by')
            ->assertSee('Indonesia');
    }

    public function test_realtime_data_is_json(): void
    {
        $this->seedTraffic();

        $this->actingAs(User::factory()->create())
            ->getJson('/dashboard/website/realtime/data')
            ->assertOk()
            ->assertJsonStructure(['active_visitors', 'pageviews_30m', 'minutes', 'pages', 'sources', 'countries', 'feed'])
            ->assertJsonPath('active_visitors', 2);
    }

    public function test_visitor_and_session_pages_show_the_journey(): void
    {
        $this->seedTraffic();
        $session = WebSession::where('channel', 'Developer Communities')->firstOrFail();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard.website.visitors.show', $session->visitor))
            ->assertOk()
            ->assertSee('/guide/installation')
            ->assertSee('outbound_click');

        $this->actingAs($user)
            ->get(route('dashboard.website.sessions.show', $session))
            ->assertOk()
            ->assertSee('203.0.113.10')
            ->assertSee('TypeError: x is undefined');
    }

    public function test_the_error_detail_shows_the_stack(): void
    {
        $this->seedTraffic();
        $error = WebError::firstOrFail();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.website.errors.show', $error->fingerprint))
            ->assertOk()
            ->assertSee('at boot (app.js:12:8)');
    }

    public function test_an_unknown_error_fingerprint_is_a_404(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.website.errors.show', str_repeat('a', 40)))
            ->assertNotFound();
    }

    public function test_the_blog_table_joins_posts_by_slug(): void
    {
        Blog::factory()->create(['title' => 'Whats new in 3.1', 'slug' => 'whats-new-3-1', 'status' => 'published', 'published_at' => now()->subDays(2)]);
        $this->sendBatch((string) Str::uuid(), (string) Str::uuid(), [$this->pageview(self::SITE.'/blog/posts/whats-new-3-1')]);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard/website/content?period=7d')
            ->assertOk()
            ->assertSee('Whats new in 3.1');
    }

    public function test_goals_can_be_created_updated_and_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('dashboard.website.goals.store'), [
            'name' => 'Read the guide', 'type' => 'pageview', 'match' => '/guide/*', 'is_active' => '1',
        ])->assertRedirect(route('dashboard.website.goals'));

        $goal = WebGoal::sole();
        $this->assertSame('/guide/*', $goal->match);

        $this->actingAs($user)->put(route('dashboard.website.goals.update', $goal), [
            'name' => 'Read the guide', 'type' => 'pageview', 'match' => '/guide/*', 'is_active' => '0',
        ])->assertRedirect();
        $this->assertFalse($goal->fresh()->is_active);

        $this->actingAs($user)->delete(route('dashboard.website.goals.destroy', $goal))->assertRedirect();
        $this->assertDatabaseCount('web_goals', 0);
    }

    public function test_an_event_goal_must_name_an_event(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('dashboard.website.goals.store'), ['name' => 'Bad', 'type' => 'event', 'match' => '/download'])
            ->assertSessionHasErrors('match');
    }

    public function test_a_goal_reports_its_conversions(): void
    {
        $this->seedTraffic();
        $goal = WebGoal::factory()->pageview('/guide/*')->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard.website.goals', ['period' => '7d', 'goal' => $goal->id]))
            ->assertOk()
            ->assertSee('Visited /guide/*');
    }

    public function test_a_funnel_is_saved_and_computed_in_order(): void
    {
        $this->seedTraffic();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('dashboard.website.funnels.store'), [
            'name' => 'Guide to GitHub',
            'scope' => 'session',
            'window_hours' => 24,
            'steps' => [
                ['type' => 'pageview', 'match' => '/guide/*', 'label' => ''],
                ['type' => 'event', 'match' => 'outbound_click', 'label' => ''],
                ['type' => 'pageview', 'match' => '', 'label' => ''],
            ],
        ])->assertRedirect();

        $funnel = WebFunnel::sole();
        $this->assertCount(2, $funnel->steps);

        $this->actingAs($user)
            ->get(route('dashboard.website.funnels.show', [$funnel, 'period' => '7d']))
            ->assertOk()
            ->assertSee('Visit: /guide/*')
            ->assertSee('Event: outbound_click');
    }

    public function test_a_funnel_needs_two_steps(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('dashboard.website.funnels.store'), [
                'name' => 'One step', 'scope' => 'session', 'window_hours' => 24,
                'steps' => [['type' => 'pageview', 'match' => '/', 'label' => '']],
            ])
            ->assertSessionHasErrors('steps');
    }

    public function test_tables_export_as_csv_with_formula_cells_neutralised(): void
    {
        // utm_campaign comes straight from a URL anyone can craft.
        $this->sendBatch((string) Str::uuid(), (string) Str::uuid(), [$this->pageview(self::SITE.'/?utm_campaign='.urlencode('=HYPERLINK("x")'))]);

        $response = $this->actingAs(User::factory()->create())->get('/dashboard/website/export?period=7d&report=breakdown&dimension=utm_campaign');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString("'=HYPERLINK", $response->streamedContent());
    }

    public function test_an_unknown_export_is_a_404(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard/website/export?report=breakdown&dimension=password')->assertNotFound();
    }

    /**
     * Two visitors, sent through the real ingestion endpoint: one from
     * Reddit reading the guide (event, click, error, vitals), one direct to
     * the download page.
     */
    private function seedTraffic(): void
    {
        $guide = (string) Str::uuid();
        $download = (string) Str::uuid();

        $this->sendBatch((string) Str::uuid(), (string) Str::uuid(), [
            $this->pageview(self::SITE.'/guide/installation?utm_source=reddit&utm_campaign=launch', 'https://www.reddit.com/r/laravel/', now()->subMinutes(3), $guide),
            $this->hit('event', $guide, ['name' => 'outbound_click', 'label' => 'https://github.com/myahyazahid/rezure', 'props' => ['position' => 'hero']]),
            $this->hit('click', $guide, ['x_pct' => 40, 'y_px' => 800, 'page_height' => 3000, 'viewport_width' => 1536, 'selector' => 'a.cta', 'text' => 'GitHub', 'is_rage' => true]),
            $this->hit('error', $guide, ['message' => 'TypeError: x is undefined', 'source' => self::SITE.'/assets/app.js', 'line' => 12, 'column' => 8, 'stack' => 'at boot (app.js:12:8)']),
            $this->hit('vitals', $guide, ['lcp_ms' => 1800, 'inp_ms' => 120, 'cls' => 0.05, 'fcp_ms' => 900, 'ttfb_ms' => 200, 'page_load_ms' => 2100]),
            $this->hit('engagement', $guide, ['engaged_ms' => 42000, 'max_scroll_pct' => 80, 'scroll_milestones' => [25, 50, 75]]),
        ], ['REMOTE_ADDR' => '203.0.113.10']);

        $this->sendBatch((string) Str::uuid(), (string) Str::uuid(), [
            $this->pageview(self::SITE.'/download', null, now()->subMinutes(2), $download),
            $this->hit('click', $download, ['x_pct' => 50, 'y_px' => 400, 'page_height' => 2000, 'viewport_width' => 1536, 'selector' => 'a.download', 'text' => 'Download']),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     * @param  array<string, string>  $server
     */
    private function sendBatch(string $visitorId, string $sessionId, array $hits, array $server = []): void
    {
        $this->postJson('/api/v1/site/hit', [
            'v' => 1,
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'sent_at' => now()->toIso8601String(),
            'context' => [
                'is_new_visitor' => true, 'visit_number' => 1, 'viewport_width' => 1536, 'viewport_height' => 864,
                'screen_width' => 1920, 'screen_height' => 1080, 'language' => 'en-US', 'timezone' => 'Asia/Jakarta',
                'color_scheme' => 'dark', 'site_theme' => 'light', 'cpu_cores' => 8, 'memory_gb' => 8, 'pixel_ratio' => 1.25,
            ],
            'hits' => $hits,
        ], ['User-Agent' => self::CHROME_UA, ...$server])->assertStatus(202);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageview(string $url, ?string $referrer = null, ?Carbon $at = null, ?string $pageviewId = null): array
    {
        return $this->hit('pageview', $pageviewId ?? (string) Str::uuid(), ['url' => $url, 'referrer' => $referrer, 'title' => 'Rezure'], $at);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function hit(string $type, string $pageviewId, array $fields, ?Carbon $at = null): array
    {
        return ['type' => $type, 'id' => (string) Str::uuid(), 'pageview_id' => $pageviewId, 'at' => ($at ?? now()->subMinute())->toIso8601String(), ...$fields];
    }
}
