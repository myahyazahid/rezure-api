<?php

namespace Tests\Feature;

use App\Models\Release;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_no_content_when_nothing_has_been_published(): void
    {
        $this->getJson('/api/v1/version/latest')->assertNoContent();
    }

    public function test_returns_the_manifest_shape_for_the_current_release(): void
    {
        Release::factory()->create([
            'version' => '1.4.0',
            'notes' => 'Latest one',
            'signature' => 'sig-contents',
            'download_url' => 'https://example.test/rezureapp_1.4.0_x64-setup.exe',
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/version/latest')
            ->assertOk()
            ->assertJson([
                'version' => '1.4.0',
                'notes' => 'Latest one',
                'platforms' => [
                    'windows-x86_64' => [
                        'signature' => 'sig-contents',
                        'url' => 'https://example.test/rezureapp_1.4.0_x64-setup.exe',
                    ],
                ],
            ])
            ->assertJsonStructure(['version', 'notes', 'pub_date', 'platforms']);
    }

    public function test_platforms_is_empty_when_the_release_has_no_signed_installer(): void
    {
        Release::factory()->create(['version' => '1.4.0', 'signature' => null, 'download_url' => null]);

        $this->getJson('/api/v1/version/latest')
            ->assertOk()
            ->assertJson(['platforms' => []]);
    }

    public function test_returns_no_content_when_the_requesting_client_is_already_current(): void
    {
        Release::factory()->create(['version' => '1.4.0']);

        $this->getJson('/api/v1/version/latest?current_version=1.4.0')->assertNoContent();
        $this->getJson('/api/v1/version/latest?current_version=1.5.0')->assertNoContent();
    }

    public function test_returns_the_manifest_when_the_requesting_client_is_behind(): void
    {
        Release::factory()->create(['version' => '1.4.0']);

        $this->getJson('/api/v1/version/latest?current_version=1.3.0')
            ->assertOk()
            ->assertJson(['version' => '1.4.0']);
    }

    public function test_current_version_query_param_is_ignored_when_absent(): void
    {
        Release::factory()->create(['version' => '1.4.0']);

        $this->getJson('/api/v1/version/latest')->assertOk()->assertJson(['version' => '1.4.0']);
    }
}
