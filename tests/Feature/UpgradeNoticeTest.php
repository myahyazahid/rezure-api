<?php

namespace Tests\Feature;

use App\Models\UpgradeNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpgradeNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_no_content_until_a_notice_is_switched_on(): void
    {
        $this->getJson('/api/v1/version/upgrade?current_version=3.0.2')->assertNoContent();
    }

    public function test_returns_no_content_while_the_notice_is_off(): void
    {
        UpgradeNotice::factory()->create(['enabled' => false, 'major' => 4]);

        $this->getJson('/api/v1/version/upgrade?current_version=3.0.2')->assertNoContent();
    }

    public function test_a_client_on_an_older_line_gets_the_notice(): void
    {
        UpgradeNotice::factory()->create([
            'major' => 4,
            'message' => 'Rezure 4 is out.',
            'url' => 'https://rezure.test/download',
        ]);

        $this->getJson('/api/v1/version/upgrade?current_version=3.0.2')
            ->assertOk()
            ->assertExactJson([
                'major' => 4,
                'message' => 'Rezure 4 is out.',
                'url' => 'https://rezure.test/download',
            ]);
    }

    public function test_a_client_on_the_announced_line_or_newer_gets_no_content(): void
    {
        UpgradeNotice::factory()->create(['major' => 4]);

        $this->getJson('/api/v1/version/upgrade?current_version=4.0.0')->assertNoContent();
        $this->getJson('/api/v1/version/upgrade?current_version=5.1.0')->assertNoContent();
    }

    public function test_a_caller_that_does_not_say_its_version_gets_the_notice_while_it_is_on(): void
    {
        UpgradeNotice::factory()->create(['major' => 4]);

        $this->getJson('/api/v1/version/upgrade')->assertOk()->assertJson(['major' => 4]);
    }

    public function test_saving_the_notice_from_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->put('/dashboard/releases/upgrade-notice', [
            'enabled' => '1',
            'major' => 4,
            'message' => 'Rezure 4 is out.',
            'url' => 'https://rezure.test/download',
        ]);

        $response->assertRedirect(route('dashboard.releases'));
        $this->assertDatabaseHas('upgrade_notices', [
            'enabled' => true,
            'major' => 4,
            'message' => 'Rezure 4 is out.',
            'url' => 'https://rezure.test/download',
        ]);
        $this->assertSame(1, UpgradeNotice::count());
    }

    public function test_unchecking_the_box_switches_the_notice_off_and_keeps_its_fields(): void
    {
        $this->actingAs(User::factory()->create());
        UpgradeNotice::factory()->create(['major' => 4, 'url' => 'https://rezure.test/download']);

        $this->put('/dashboard/releases/upgrade-notice', [
            'enabled' => '0',
            'major' => 4,
            'url' => 'https://rezure.test/download',
        ])->assertRedirect(route('dashboard.releases'));

        $notice = UpgradeNotice::current();
        $this->assertFalse($notice->enabled);
        $this->assertSame('https://rezure.test/download', $notice->url);
    }

    public function test_a_switched_on_notice_needs_a_major_a_message_and_a_link(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put('/dashboard/releases/upgrade-notice', ['enabled' => '1'])
            ->assertSessionHasErrorsIn('upgradeNotice', ['major', 'message', 'url']);
    }

    public function test_the_link_must_be_a_web_address(): void
    {
        $this->actingAs(User::factory()->create());

        foreach (['file:///C:/Windows/System32/cmd.exe', 'javascript:alert(1)'] as $url) {
            $this->put('/dashboard/releases/upgrade-notice', [
                'enabled' => '1',
                'major' => 4,
                'message' => 'Rezure 4 is out.',
                'url' => $url,
            ])->assertSessionHasErrorsIn('upgradeNotice', 'url');
        }
    }
}
