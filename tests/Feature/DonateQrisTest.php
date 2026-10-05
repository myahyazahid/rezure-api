<?php

namespace Tests\Feature;

use App\Models\DonateConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DonateQrisTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR";

    private const JPEG = "\xFF\xD8\xFF\xE0\x00\x10JFIF";

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function signIn(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function upload(string $name, string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $contents);
    }

    public function test_api_sends_a_null_qris_when_none_was_uploaded(): void
    {
        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJsonPath('qris', null);

        $this->get('/api/v1/support/donate/qris')->assertNotFound();
    }

    public function test_an_uploaded_qris_is_described_from_its_own_bytes(): void
    {
        $this->signIn();

        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('whatever-the-browser-called-it.jpg', self::PNG)])
            ->assertRedirect(route('dashboard.donate'));

        Storage::disk('local')->assertExists('donate/qris.png');
        $this->assertSame('png', DonateConfig::current()->qris_format);

        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJsonPath('qris', [
                'format' => 'png',
                'size' => strlen(self::PNG),
                'sha256' => hash('sha256', self::PNG),
                'url' => route('api.v1.support.donate.qris'),
            ]);
    }

    public function test_the_api_serves_the_image_with_its_hash_as_etag(): void
    {
        $this->signIn();
        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.png', self::PNG)]);

        $response = $this->get('/api/v1/support/donate/qris')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame('"'.hash('sha256', self::PNG).'"', $response->headers->get('ETag'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));

        $this->withHeader('If-None-Match', '"'.hash('sha256', self::PNG).'"')
            ->get('/api/v1/support/donate/qris')
            ->assertStatus(304);
    }

    public function test_only_png_jpeg_and_webp_are_accepted(): void
    {
        $this->signIn();

        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.png', 'just some text')])
            ->assertSessionHasErrors('qris');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>';
        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.svg', $svg)])
            ->assertSessionHasErrors('qris');

        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.jpg', self::JPEG)])
            ->assertSessionHasNoErrors();

        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.webp', "RIFF\x00\x00\x00\x00WEBPVP8 ")])
            ->assertSessionHasNoErrors();
    }

    public function test_a_file_over_the_size_limit_is_rejected(): void
    {
        $this->signIn();
        $tooBig = self::PNG.str_repeat("\x00", DonateConfig::MAX_QRIS_BYTES);

        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.png', $tooBig)])
            ->assertSessionHasErrors('qris');

        $this->assertFalse(DonateConfig::current()->hasQris());
    }

    public function test_the_file_is_required(): void
    {
        $this->signIn();

        $this->put('/dashboard/donate/qris', [])->assertSessionHasErrors('qris');
    }

    public function test_replacing_with_another_format_removes_the_old_file(): void
    {
        $this->signIn();
        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.png', self::PNG)]);

        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.jpg', self::JPEG)]);

        Storage::disk('local')->assertMissing('donate/qris.png');
        Storage::disk('local')->assertExists('donate/qris.jpg');
        $this->assertSame(hash('sha256', self::JPEG), DonateConfig::current()->qris_sha256);
    }

    public function test_removing_the_qris_clears_it_from_the_api(): void
    {
        $this->signIn();
        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.png', self::PNG)]);

        $this->delete('/dashboard/donate/qris')->assertRedirect(route('dashboard.donate'));

        Storage::disk('local')->assertMissing('donate/qris.png');
        $this->getJson('/api/v1/support/donate')->assertJsonPath('qris', null);
        $this->get('/api/v1/support/donate/qris')->assertNotFound();
    }

    public function test_removing_when_there_is_no_qris_is_harmless(): void
    {
        $this->signIn();

        $this->delete('/dashboard/donate/qris')->assertRedirect(route('dashboard.donate'));
    }

    public function test_uploading_makes_cached_clients_fetch_the_donate_payload_again(): void
    {
        $this->signIn();
        $config = DonateConfig::current();
        $staleLastModified = $config->updated_at->setTimezone('UTC')->format('D, d M Y H:i:s').' GMT';

        $this->travel(1)->seconds();
        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.png', self::PNG)]);

        $this->withHeader('If-Modified-Since', $staleLastModified)
            ->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJsonPath('qris.sha256', hash('sha256', self::PNG));
    }

    public function test_the_dashboard_page_shows_the_upload_form_and_then_the_preview(): void
    {
        $this->signIn();

        $this->get('/dashboard/donate')
            ->assertOk()
            ->assertSee('Upload QRIS')
            ->assertDontSee('Remove QRIS');

        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.png', self::PNG)]);

        $this->get('/dashboard/donate')
            ->assertOk()
            ->assertSee('Replace QRIS')
            ->assertSee('Remove QRIS');

        $this->get('/dashboard/donate/qris/preview')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_the_preview_is_a_404_without_a_qris(): void
    {
        $this->signIn();

        $this->get('/dashboard/donate/qris/preview')->assertNotFound();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/dashboard/donate/qris/preview')->assertRedirect(route('login'));
        $this->put('/dashboard/donate/qris', ['qris' => $this->upload('qris.png', self::PNG)])->assertRedirect(route('login'));
        $this->delete('/dashboard/donate/qris')->assertRedirect(route('login'));

        $this->assertFalse(DonateConfig::current()->hasQris());
    }
}
