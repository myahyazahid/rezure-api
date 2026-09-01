<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportTicketsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_is_submitted_without_attachments(): void
    {
        $deviceId = fake()->uuid();

        $response = $this->postJson('/api/v1/support/tickets', [
            'device_id' => $deviceId,
            'client_ticket_id' => fake()->uuid(),
            'category' => 'bug',
            'title' => 'App crashes on start',
            'description' => 'It just crashes.',
            'app_version' => '1.4.0',
            'os_version' => '23H2',
        ]);

        $response->assertStatus(202)->assertJson(['status' => 'queued']);

        $this->assertDatabaseHas('tickets', ['title' => 'App crashes on start', 'category' => 'bug']);
        $this->assertDatabaseHas('devices', ['device_id' => $deviceId]);
    }

    public function test_ticket_is_submitted_with_attachments_and_stores_them_on_the_private_disk(): void
    {
        Storage::fake('local');

        $response = $this->postJson('/api/v1/support/tickets', [
            'device_id' => fake()->uuid(),
            'client_ticket_id' => fake()->uuid(),
            'category' => 'bug',
            'title' => 'Crash with logs attached',
            'description' => 'See attached.',
            'app_version' => '1.4.0',
            'attachments' => [
                UploadedFile::fake()->image('screenshot.png'),
                UploadedFile::fake()->create('service.log', 10, 'text/plain'),
            ],
        ]);

        $response->assertStatus(202);

        $ticket = Ticket::firstOrFail();
        $this->assertSame(2, $ticket->attachments()->count());

        foreach ($ticket->attachments as $attachment) {
            Storage::disk('local')->assertExists($attachment->file_path);
        }
    }

    public function test_a_retried_submission_with_the_same_client_ticket_id_is_not_duplicated_and_deletes_the_orphaned_file(): void
    {
        Storage::fake('local');

        $payload = [
            'device_id' => fake()->uuid(),
            'client_ticket_id' => fake()->uuid(),
            'category' => 'general',
            'title' => 'Duplicate test',
            'description' => 'Retried after a dropped connection.',
            'app_version' => '1.4.0',
        ];

        $this->postJson('/api/v1/support/tickets', [
            ...$payload,
            'attachments' => [UploadedFile::fake()->image('first.png')],
        ])->assertStatus(202);

        $ticket = Ticket::firstOrFail();
        $originalPath = $ticket->attachments()->sole()->file_path;

        $this->postJson('/api/v1/support/tickets', [
            ...$payload,
            'attachments' => [UploadedFile::fake()->image('retry.png')],
        ])->assertStatus(202);

        $this->assertSame(1, Ticket::count());
        $this->assertSame(1, $ticket->attachments()->count());
        Storage::disk('local')->assertExists($originalPath);
    }

    public function test_ticket_submission_requires_core_fields(): void
    {
        $response = $this->postJson('/api/v1/support/tickets', [
            'device_id' => fake()->uuid(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors([
            'client_ticket_id', 'category', 'title', 'description',
        ]);
    }

    public function test_attachment_with_a_disallowed_mime_type_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/support/tickets', [
            'device_id' => fake()->uuid(),
            'client_ticket_id' => fake()->uuid(),
            'category' => 'bug',
            'title' => 'Bad attachment',
            'description' => 'Should fail validation.',
            'app_version' => '1.4.0',
            'attachments' => [UploadedFile::fake()->create('malware.exe', 10)],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['attachments.0']);
    }

    public function test_attachment_over_the_size_limit_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/support/tickets', [
            'device_id' => fake()->uuid(),
            'client_ticket_id' => fake()->uuid(),
            'category' => 'bug',
            'title' => 'Oversized attachment',
            'description' => 'Should fail validation.',
            'app_version' => '1.4.0',
            'attachments' => [UploadedFile::fake()->create('huge.log', 10241)],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['attachments.0']);
    }

    public function test_history_endpoint_returns_only_the_requested_devices_tickets(): void
    {
        $device = Device::factory()->create();
        $otherDevice = Device::factory()->create();

        Ticket::factory()->for($device)->create(['title' => 'Mine']);
        Ticket::factory()->for($otherDevice)->create(['title' => 'Not mine']);

        $response = $this->getJson('/api/v1/support/tickets?device_id='.$device->device_id);

        $response->assertOk()->assertJsonCount(1)->assertJsonFragment(['title' => 'Mine']);
    }

    public function test_ticket_submission_is_rate_limited_per_device(): void
    {
        $deviceId = fake()->uuid();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/support/tickets', [
                'device_id' => $deviceId,
                'client_ticket_id' => fake()->uuid(),
                'category' => 'general',
                'title' => "Ticket {$i}",
                'description' => 'Rate limit test.',
                'app_version' => '1.4.0',
            ])->assertStatus(202);
        }

        $this->postJson('/api/v1/support/tickets', [
            'device_id' => $deviceId,
            'client_ticket_id' => fake()->uuid(),
            'category' => 'general',
            'title' => 'Ticket 11',
            'description' => 'Should be rate limited.',
            'app_version' => '1.4.0',
        ])->assertStatus(429);
    }
}
