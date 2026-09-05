<?php

namespace Tests\Feature;

use App\Models\Changelog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangelogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_changelog_page_renders_with_no_data(): void
    {
        $this->get('/dashboard/changelog')->assertOk();
    }

    public function test_creating_a_changelog_entry_persists_it_and_redirects_back(): void
    {
        $response = $this->post('/dashboard/changelog', [
            'version' => '1.5.0',
            'title' => 'Cloudflare tunnel support',
            'body' => 'Added Cloudflare tunnel support and mkcert auto-HTTPS.',
            'released_at' => now()->toDateTimeString(),
        ]);

        $response->assertRedirect(route('dashboard.changelog'));
        $this->assertDatabaseHas('changelogs', ['version' => '1.5.0', 'title' => 'Cloudflare tunnel support']);
    }

    public function test_creating_requires_all_fields(): void
    {
        $response = $this->post('/dashboard/changelog', ['title' => 'no version here']);

        $response->assertSessionHasErrors(['version', 'body', 'released_at']);
    }

    public function test_editing_prefills_the_form(): void
    {
        $entry = Changelog::factory()->create(['title' => 'Existing entry']);

        $this->get(route('dashboard.changelog', ['edit' => $entry->id]))
            ->assertOk()
            ->assertSee('Existing entry');
    }

    public function test_updating_a_changelog_entry_persists_and_redirects(): void
    {
        $entry = Changelog::factory()->create();

        $response = $this->put(route('dashboard.changelog.update', $entry), [
            'version' => $entry->version,
            'title' => 'Updated title',
            'body' => $entry->body,
            'released_at' => $entry->released_at->toDateTimeString(),
        ]);

        $response->assertRedirect(route('dashboard.changelog'));
        $this->assertDatabaseHas('changelogs', ['id' => $entry->id, 'title' => 'Updated title']);
    }

    public function test_deleting_a_changelog_entry_removes_it_and_redirects(): void
    {
        $entry = Changelog::factory()->create();

        $response = $this->delete(route('dashboard.changelog.destroy', $entry));

        $response->assertRedirect(route('dashboard.changelog'));
        $this->assertDatabaseMissing('changelogs', ['id' => $entry->id]);
    }

    public function test_public_changelog_endpoint_returns_entries_newest_first(): void
    {
        Changelog::factory()->create(['version' => '1.3.0', 'released_at' => now()->subDays(5)]);
        Changelog::factory()->create(['version' => '1.4.0', 'released_at' => now()]);

        $response = $this->getJson('/api/v1/changelog');

        $response->assertOk();
        $this->assertSame('1.4.0', $response->json('0.version'));
        $this->assertSame('1.3.0', $response->json('1.version'));
    }

    public function test_public_changelog_endpoint_returns_an_empty_array_when_nothing_published(): void
    {
        $response = $this->getJson('/api/v1/changelog');

        $response->assertOk()->assertExactJson([]);
    }

    public function test_public_changelog_endpoint_caps_at_twenty_entries(): void
    {
        Changelog::factory()->count(21)->create();

        $response = $this->getJson('/api/v1/changelog');

        $response->assertOk()->assertJsonCount(20);
    }
}
