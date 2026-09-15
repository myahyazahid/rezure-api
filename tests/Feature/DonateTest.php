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
