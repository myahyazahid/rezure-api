<?php

namespace Tests\Feature;

use App\Models\DonateConfig;
use App\Models\DonateMethod;
use App\Models\User;
use Database\Factories\StickerFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DonateMethodIconTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function icon(string $name, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bitcoin(array $overrides = []): array
    {
        return [
            'category' => 'crypto',
            'preset' => 'btc',
            'label' => 'Bitcoin',
            'symbol' => 'BTC',
            'network' => 'Bitcoin',
            'address' => 'bc1qexample',
            ...$overrides,
        ];
    }

    private function addBitcoinWithIcon(string $name = 'btc.png', string $contents = self::PNG): DonateMethod
    {
        $this->post('/dashboard/donate', $this->bitcoin(['icon' => $this->icon($name, $contents)]));

        return DonateMethod::query()->latest('id')->firstOrFail();
    }

    public function test_a_wallet_without_an_icon_sends_a_null_icon(): void
    {
        DonateMethod::factory()->crypto()->create();

        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJsonPath('crypto.0.icon', null);
    }

    public function test_an_uploaded_icon_is_described_from_its_own_bytes(): void
    {
        $this->signIn();

        $method = $this->addBitcoinWithIcon('whatever-the-browser-called-it.jpg');

        Storage::disk('local')->assertExists("donate/icons/{$method->id}.png");

        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJsonPath('crypto.0.id', $method->id)
            ->assertJsonPath('crypto.0.icon', [
                'format' => 'png',
                'size' => strlen(self::PNG),
                'sha256' => hash('sha256', self::PNG),
                'url' => route('api.v1.support.donate.icon', $method),
            ]);
    }

    public function test_the_api_serves_the_icon_with_its_hash_as_etag(): void
    {
        $this->signIn();
        $method = $this->addBitcoinWithIcon();

        $response = $this->get("/api/v1/support/donate/methods/{$method->id}/icon")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame('"'.hash('sha256', self::PNG).'"', $response->headers->get('ETag'));

        $this->withHeader('If-None-Match', '"'.hash('sha256', self::PNG).'"')
            ->get("/api/v1/support/donate/methods/{$method->id}/icon")
            ->assertStatus(304);
    }

    public function test_an_svg_icon_is_accepted_and_served_sandboxed(): void
    {
        $this->signIn();
        $method = $this->addBitcoinWithIcon('btc.svg', StickerFactory::SVG);

        $this->assertSame('svg', $method->icon_format);

        $this->get("/api/v1/support/donate/methods/{$method->id}/icon")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }

    public function test_an_unsafe_or_non_image_file_is_refused_in_terms_of_icons(): void
    {
        $this->signIn();

        $this->post('/dashboard/donate', $this->bitcoin(['icon' => $this->icon('btc.png', 'just some text')]))
            ->assertSessionHasErrors('icon');

        $unsafe = '<svg xmlns="http://www.w3.org/2000/svg"><script>x()</script></svg>';
        $response = $this->post('/dashboard/donate', $this->bitcoin(['icon' => $this->icon('btc.svg', $unsafe)]));

        $response->assertSessionHasErrors('icon');
        $this->assertStringContainsString("which icons can't use", session('errors')->first('icon'));
        $this->assertDatabaseCount('donate_methods', 0);
    }

    public function test_a_file_over_the_size_limit_is_refused(): void
    {
        $this->signIn();
        $tooBig = self::PNG.str_repeat("\x00", DonateMethod::MAX_ICON_BYTES);

        $this->post('/dashboard/donate', $this->bitcoin(['icon' => $this->icon('btc.png', $tooBig)]))
            ->assertSessionHasErrors('icon');
    }

    public function test_a_link_takes_an_icon_too(): void
    {
        $this->signIn();

        $this->post('/dashboard/donate', [
            'category' => 'global',
            'preset' => 'custom',
            'label' => 'GitHub Stars',
            'url' => 'https://github.com/myahyazahid/rezure',
            'icon' => $this->icon('github.svg', StickerFactory::SVG),
        ])->assertSessionHasNoErrors();

        $method = DonateMethod::query()->firstOrFail();
        Storage::disk('local')->assertExists("donate/icons/{$method->id}.svg");

        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJsonPath('global.0.id', $method->id)
            ->assertJsonPath('global.0.label', 'GitHub Stars')
            ->assertJsonPath('global.0.icon', [
                'format' => 'svg',
                'size' => strlen(StickerFactory::SVG),
                'sha256' => hash('sha256', StickerFactory::SVG),
                'url' => route('api.v1.support.donate.icon', $method),
            ]);

        $this->get("/api/v1/support/donate/methods/{$method->id}/icon")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_a_link_without_an_icon_sends_a_null_icon(): void
    {
        DonateMethod::factory()->local()->create();

        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJsonPath('local.0.icon', null);
    }

    public function test_leaving_the_icon_empty_on_edit_keeps_the_current_one(): void
    {
        $this->signIn();
        $method = $this->addBitcoinWithIcon();

        $this->put("/dashboard/donate/{$method->id}", $this->bitcoin(['label' => 'Bitcoin (BTC)']))
            ->assertRedirect(route('dashboard.donate'));

        $method->refresh();
        $this->assertSame('Bitcoin (BTC)', $method->label);
        $this->assertSame(hash('sha256', self::PNG), $method->icon_sha256);
        Storage::disk('local')->assertExists("donate/icons/{$method->id}.png");
    }

    public function test_replacing_with_another_format_removes_the_old_file(): void
    {
        $this->signIn();
        $method = $this->addBitcoinWithIcon();

        $this->put("/dashboard/donate/{$method->id}", $this->bitcoin(['icon' => $this->icon('btc.svg', StickerFactory::SVG)]));

        Storage::disk('local')->assertMissing("donate/icons/{$method->id}.png");
        Storage::disk('local')->assertExists("donate/icons/{$method->id}.svg");
        $this->assertSame(hash('sha256', StickerFactory::SVG), $method->fresh()->icon_sha256);
    }

    public function test_remove_icon_clears_it(): void
    {
        $this->signIn();
        $method = $this->addBitcoinWithIcon();

        $this->put("/dashboard/donate/{$method->id}", $this->bitcoin(['remove_icon' => '1']));

        Storage::disk('local')->assertMissing("donate/icons/{$method->id}.png");
        $this->assertFalse($method->fresh()->hasIcon());
        $this->getJson('/api/v1/support/donate')->assertJsonPath('crypto.0.icon', null);
        $this->get("/api/v1/support/donate/methods/{$method->id}/icon")->assertNotFound();
    }

    public function test_deleting_the_wallet_deletes_its_icon_file(): void
    {
        $this->signIn();
        $method = $this->addBitcoinWithIcon();

        $this->delete("/dashboard/donate/{$method->id}")->assertRedirect(route('dashboard.donate'));

        Storage::disk('local')->assertMissing("donate/icons/{$method->id}.png");
    }

    public function test_the_icon_route_is_a_404_without_an_icon_or_for_an_unknown_method(): void
    {
        $wallet = DonateMethod::factory()->crypto()->create();
        $link = DonateMethod::factory()->local()->create();

        $this->get("/api/v1/support/donate/methods/{$wallet->id}/icon")->assertNotFound();
        $this->get("/api/v1/support/donate/methods/{$link->id}/icon")->assertNotFound();
        $this->get('/api/v1/support/donate/methods/999999/icon')->assertNotFound();
    }

    public function test_changing_an_icon_makes_cached_clients_fetch_the_donate_payload_again(): void
    {
        $this->signIn();
        $method = DonateMethod::factory()->crypto()->create();
        $config = DonateConfig::current();
        $staleLastModified = $config->updated_at->setTimezone('UTC')->format('D, d M Y H:i:s').' GMT';

        $this->travel(1)->seconds();
        $this->put("/dashboard/donate/{$method->id}", $this->bitcoin(['icon' => $this->icon('btc.png', self::PNG)]));

        $this->withHeader('If-Modified-Since', $staleLastModified)
            ->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJsonPath('crypto.0.icon.sha256', hash('sha256', self::PNG));
    }

    public function test_the_dashboard_shows_the_icon_in_the_list_and_on_the_edit_form(): void
    {
        $this->signIn();
        $method = $this->addBitcoinWithIcon();
        $previewUrl = route('dashboard.donate.icon.preview', ['donateMethod' => $method, 'v' => $method->icon_sha256]);

        $this->get('/dashboard/donate')->assertOk()->assertSee($previewUrl, false);
        $this->get("/dashboard/donate/{$method->id}/edit")->assertOk()->assertSee('Remove icon')->assertSee($previewUrl, false);

        $this->get("/dashboard/donate/{$method->id}/icon")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_the_create_form_offers_an_icon_upload_for_every_category(): void
    {
        $this->signIn();

        foreach (['local', 'global', 'crypto'] as $category) {
            $this->get("/dashboard/donate/create?category={$category}&preset=custom")
                ->assertOk()
                ->assertSee('name="icon"', false)
                ->assertSee('multipart/form-data', false);
        }
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $method = DonateMethod::factory()->crypto()->create();

        $this->get("/dashboard/donate/{$method->id}/icon")->assertRedirect(route('login'));
        $this->post('/dashboard/donate', $this->bitcoin(['icon' => $this->icon('btc.png', self::PNG)]))->assertRedirect(route('login'));

        $this->assertDatabaseCount('donate_methods', 1);
    }
}
