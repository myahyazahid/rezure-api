<?php

namespace Tests\Feature;

use App\Models\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_releases_page_renders_with_no_data(): void
    {
        $this->get('/dashboard/releases')->assertOk();
    }

    public function test_publishing_a_release_creates_it_and_redirects_back(): void
    {
        $response = $this->post('/dashboard/releases', [
            'version' => '1.5.0',
            'notes' => 'Bug fixes and improvements.',
        ]);

        $response->assertRedirect(route('dashboard.releases'));
        $this->assertDatabaseHas('releases', ['version' => '1.5.0', 'notes' => 'Bug fixes and improvements.']);
    }

    public function test_publishing_requires_a_version(): void
    {
        $response = $this->post('/dashboard/releases', ['notes' => 'no version here']);

        $response->assertSessionHasErrors('version');
    }

    public function test_current_release_is_the_most_recently_published_row(): void
    {
        Release::factory()->create(['version' => '1.3.0', 'published_at' => now()->subDays(5)]);
        Release::factory()->create(['version' => '1.4.0', 'published_at' => now()->subDay()]);

        $this->assertSame('1.4.0', Release::current()->version);
    }

    public function test_version_latest_endpoint_returns_the_current_release(): void
    {
        Release::factory()->create(['version' => '1.3.0', 'published_at' => now()->subDay()]);
        Release::factory()->create(['version' => '1.4.0', 'notes' => 'Latest one', 'published_at' => now()]);

        $response = $this->getJson('/api/v1/version/latest');

        $response->assertOk()->assertJson([
            'version' => '1.4.0',
            'notes' => 'Latest one',
        ]);
    }

    public function test_version_latest_endpoint_returns_no_content_when_nothing_published(): void
    {
        $this->getJson('/api/v1/version/latest')->assertNoContent();
    }

    public function test_publishing_accepts_an_updater_signature_and_download_url(): void
    {
        $response = $this->post('/dashboard/releases', [
            'version' => '1.5.0',
            'signature' => 'sig-contents',
            'download_url' => 'https://example.test/rezureapp_1.5.0_x64-setup.exe',
        ]);

        $response->assertRedirect(route('dashboard.releases'));
        $this->assertDatabaseHas('releases', [
            'version' => '1.5.0',
            'signature' => 'sig-contents',
            'download_url' => 'https://example.test/rezureapp_1.5.0_x64-setup.exe',
        ]);
    }

    public function test_publishing_requires_a_download_url_when_a_signature_is_given(): void
    {
        $response = $this->post('/dashboard/releases', [
            'version' => '1.5.0',
            'signature' => 'sig-contents',
        ]);

        $response->assertSessionHasErrors('download_url');
    }
}
