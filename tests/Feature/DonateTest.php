<?php

namespace Tests\Feature;

use App\Models\DonateConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonateTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_default_empty_config_when_nothing_has_been_configured(): void
    {
        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJson([
                'local' => [],
                'global' => [],
                'crypto' => [],
            ])
            ->assertJsonStructure(['message', 'local', 'global', 'crypto']);
    }

    public function test_api_returns_the_configured_donate_settings(): void
    {
        DonateConfig::factory()->create([
            'message' => 'Support Rezure',
            'local' => [['label' => 'Trakteer', 'url' => 'https://trakteer.id/example']],
            'global' => [],
            'crypto' => [['symbol' => 'BTC', 'label' => 'Bitcoin', 'address' => 'bc1qexample']],
        ]);

        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJson([
                'message' => 'Support Rezure',
                'local' => [['label' => 'Trakteer', 'url' => 'https://trakteer.id/example']],
                'global' => [],
                'crypto' => [['symbol' => 'BTC', 'label' => 'Bitcoin', 'address' => 'bc1qexample']],
            ]);
    }

    public function test_api_response_carries_a_last_modified_header(): void
    {
        $config = DonateConfig::factory()->create();

        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertHeader('Last-Modified', $config->updated_at->setTimezone('UTC')->format('D, d M Y H:i:s').' GMT');
    }

    public function test_api_returns_not_modified_when_the_client_already_has_the_current_version(): void
    {
        $config = DonateConfig::factory()->create();
        $lastModified = $config->updated_at->setTimezone('UTC')->format('D, d M Y H:i:s').' GMT';

        $this->withHeader('If-Modified-Since', $lastModified)
            ->getJson('/api/v1/support/donate')
            ->assertStatus(304)
            ->assertNoContent(304);
    }

    public function test_api_returns_fresh_data_after_the_config_changes(): void
    {
        $config = DonateConfig::factory()->create();
        $staleLastModified = $config->updated_at->setTimezone('UTC')->format('D, d M Y H:i:s').' GMT';

        // HTTP dates only carry second precision, and Eloquent stamps
        // updated_at from the real clock (it's not fillable) — travel
        // forward so a fast test run can't land in the same second as
        // creation and produce a false "not modified".
        $this->travel(1)->seconds();
        $config->update(['message' => 'Updated message']);

        $this->withHeader('If-Modified-Since', $staleLastModified)
            ->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJson(['message' => 'Updated message']);
    }

    public function test_dashboard_donate_page_renders(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard/donate')->assertOk();
    }

    public function test_dashboard_update_saves_the_config_and_drops_blank_rows(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->put('/dashboard/donate', [
            'message' => 'Support Rezure',
            'local' => [
                ['label' => 'Trakteer', 'url' => 'https://trakteer.id/example'],
                ['label' => '', 'url' => ''],
            ],
            'global' => [
                ['label' => '', 'url' => ''],
            ],
            'crypto' => [
                ['symbol' => 'BTC', 'label' => 'Bitcoin', 'address' => 'bc1qexample'],
            ],
        ]);

        $response->assertRedirect(route('dashboard.donate'));

        $config = DonateConfig::current();
        $this->assertSame('Support Rezure', $config->message);
        $this->assertSame([['label' => 'Trakteer', 'url' => 'https://trakteer.id/example']], $config->local);
        $this->assertSame([], $config->global);
        $this->assertSame([['symbol' => 'BTC', 'label' => 'Bitcoin', 'address' => 'bc1qexample']], $config->crypto);
    }

    public function test_dashboard_update_requires_a_message(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->put('/dashboard/donate', ['message' => '']);

        $response->assertSessionHasErrors('message');
    }
}
