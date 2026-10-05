<?php

namespace Tests\Feature;

use App\Models\DonateConfig;
use App\Models\DonateMethod;
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

    public function test_api_returns_the_configured_donate_methods_grouped_by_category(): void
    {
        DonateConfig::factory()->create(['message' => 'Support Rezure']);
        DonateMethod::factory()->local()->create(['label' => 'Trakteer', 'url' => 'https://trakteer.id/example']);
        DonateMethod::factory()->crypto()->create(['symbol' => 'BTC', 'network' => 'Bitcoin', 'label' => 'Bitcoin', 'address' => 'bc1qexample']);

        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJson([
                'message' => 'Support Rezure',
                'local' => [['label' => 'Trakteer', 'url' => 'https://trakteer.id/example']],
                'global' => [],
                'crypto' => [['symbol' => 'BTC', 'network' => 'Bitcoin', 'label' => 'Bitcoin', 'address' => 'bc1qexample']],
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

    public function test_api_returns_fresh_data_after_the_message_changes(): void
    {
        $config = DonateConfig::factory()->create();
        $staleLastModified = $config->updated_at->setTimezone('UTC')->format('D, d M Y H:i:s').' GMT';

        // HTTP dates only carry second precision, and Eloquent stamps
        // updated_at from the real clock — travel forward so a fast test
        // run can't land in the same second as creation and produce a
        // false "not modified".
        $this->travel(1)->seconds();
        $config->update(['message' => 'Updated message']);

        $this->withHeader('If-Modified-Since', $staleLastModified)
            ->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJson(['message' => 'Updated message']);
    }

    public function test_api_returns_fresh_data_after_a_method_is_added(): void
    {
        $config = DonateConfig::factory()->create();
        $staleLastModified = $config->updated_at->setTimezone('UTC')->format('D, d M Y H:i:s').' GMT';

        $this->travel(1)->seconds();
        $this->actingAs(User::factory()->create())->post('/dashboard/donate', [
            'category' => 'local',
            'preset' => 'trakteer',
            'label' => 'Trakteer',
            'url' => 'https://trakteer.id/example',
        ]);

        $this->withHeader('If-Modified-Since', $staleLastModified)
            ->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJson(['local' => [['label' => 'Trakteer', 'url' => 'https://trakteer.id/example']]]);
    }

    public function test_dashboard_donate_index_renders(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard/donate')->assertOk();
    }

    public function test_dashboard_update_message(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->put('/dashboard/donate/message', ['message' => 'Support Rezure']);

        $response->assertRedirect(route('dashboard.donate'));
        $this->assertSame('Support Rezure', DonateConfig::current()->fresh()->message);
    }

    public function test_dashboard_update_message_requires_a_message(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->put('/dashboard/donate/message', ['message' => '']);

        $response->assertSessionHasErrors('message');
    }

    public function test_dashboard_create_shows_a_preset_picker_when_none_is_chosen(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard/donate/create?category=local')
            ->assertOk()
            ->assertSee('Trakteer')
            ->assertSee('Custom');
    }

    public function test_dashboard_create_rejects_an_unknown_category(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard/donate/create?category=nope')->assertNotFound();
    }

    public function test_dashboard_can_add_a_method_from_a_preset(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->post('/dashboard/donate', [
            'category' => 'crypto',
            'preset' => 'btc',
            'label' => 'Bitcoin',
            'symbol' => 'BTC',
            'network' => 'Bitcoin',
            'address' => 'bc1qexample',
        ]);

        $response->assertRedirect(route('dashboard.donate'));
        $this->assertDatabaseHas('donate_methods', [
            'category' => 'crypto',
            'preset' => 'btc',
            'label' => 'Bitcoin',
            'symbol' => 'BTC',
            'network' => 'Bitcoin',
            'address' => 'bc1qexample',
        ]);
    }

    public function test_dashboard_create_form_prefills_the_network_from_a_crypto_preset(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard/donate/create?category=crypto&preset=usdt')
            ->assertOk()
            ->assertSee('value="Tether"', false)
            ->assertSee('value="Tron (TRC-20)"', false);
    }

    public function test_dashboard_network_is_a_dropdown_with_the_presets_network_selected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard/donate/create?category=crypto&preset=usdt')
            ->assertOk()
            ->assertSee('<select', false)
            ->assertSee('<option value="Tron (TRC-20)" selected>', false)
            ->assertSee('<option value="Base (Ethereum L2)"', false)
            ->assertDontSee('<datalist', false);
    }

    public function test_dashboard_edit_form_opens_other_for_a_network_that_is_not_listed(): void
    {
        $this->actingAs(User::factory()->create());
        $method = DonateMethod::factory()->crypto()->create(['network' => 'Some Custom Chain']);

        $this->get("/dashboard/donate/{$method->id}/edit")
            ->assertOk()
            ->assertSee('<option value="__other__" selected>', false)
            ->assertSee('value="Some Custom Chain"', false);
    }

    public function test_dashboard_can_update_a_wallets_network(): void
    {
        $this->actingAs(User::factory()->create());
        $method = DonateMethod::factory()->crypto()->create(['network' => null]);

        $this->put("/dashboard/donate/{$method->id}", [
            'category' => 'crypto',
            'preset' => 'btc',
            'label' => 'Bitcoin',
            'symbol' => 'BTC',
            'network' => 'Bitcoin',
            'address' => $method->address,
        ])->assertRedirect(route('dashboard.donate'));

        $this->assertSame('Bitcoin', $method->fresh()->network);
    }

    public function test_api_sends_a_null_network_for_a_wallet_saved_before_the_field_existed(): void
    {
        DonateMethod::factory()->crypto()->create(['network' => null]);

        $this->getJson('/api/v1/support/donate')
            ->assertOk()
            ->assertJsonPath('crypto.0.network', null);
    }

    public function test_dashboard_can_add_a_custom_method(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->post('/dashboard/donate', [
            'category' => 'global',
            'preset' => 'custom',
            'label' => 'My Own Page',
            'url' => 'https://example.test/support',
        ]);

        $response->assertRedirect(route('dashboard.donate'));
        $this->assertDatabaseHas('donate_methods', [
            'category' => 'global',
            'preset' => 'custom',
            'label' => 'My Own Page',
            'url' => 'https://example.test/support',
        ]);
    }

    public function test_dashboard_adding_a_link_method_requires_a_url(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->post('/dashboard/donate', [
            'category' => 'local',
            'preset' => 'custom',
            'label' => 'No URL',
        ]);

        $response->assertSessionHasErrors('url');
    }

    public function test_dashboard_adding_a_crypto_method_requires_symbol_network_and_address(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->post('/dashboard/donate', [
            'category' => 'crypto',
            'preset' => 'custom',
            'label' => 'No details',
        ]);

        $response->assertSessionHasErrors(['symbol', 'network', 'address']);
    }

    public function test_dashboard_edit_page_renders_for_an_existing_method(): void
    {
        $this->actingAs(User::factory()->create());
        $method = DonateMethod::factory()->local()->create();

        $this->get("/dashboard/donate/{$method->id}/edit")
            ->assertOk()
            ->assertSee($method->label);
    }

    public function test_dashboard_can_update_a_method(): void
    {
        $this->actingAs(User::factory()->create());
        $method = DonateMethod::factory()->local()->create(['label' => 'Old label']);

        $response = $this->put("/dashboard/donate/{$method->id}", [
            'category' => 'local',
            'preset' => $method->preset,
            'label' => 'New label',
            'url' => 'https://example.test/new',
        ]);

        $response->assertRedirect(route('dashboard.donate'));
        $this->assertSame('New label', $method->fresh()->label);
    }

    public function test_dashboard_can_delete_a_method(): void
    {
        $this->actingAs(User::factory()->create());
        $method = DonateMethod::factory()->local()->create();

        $response = $this->delete("/dashboard/donate/{$method->id}");

        $response->assertRedirect(route('dashboard.donate'));
        $this->assertDatabaseMissing('donate_methods', ['id' => $method->id]);
    }
}
