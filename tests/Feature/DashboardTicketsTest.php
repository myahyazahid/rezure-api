<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DashboardTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tickets_index_renders_with_no_data(): void
    {
        $this->get('/dashboard/tickets')->assertOk();
    }

    public function test_tickets_index_filters_by_status_and_category(): void
    {
        Ticket::factory()->create(['status' => 'open', 'category' => 'bug', 'title' => 'Open bug']);
        Ticket::factory()->create(['status' => 'resolved', 'category' => 'general', 'title' => 'Resolved general']);

        $response = $this->get('/dashboard/tickets?status=open&category=bug');

        $response->assertOk()->assertSee('Open bug')->assertDontSee('Resolved general');
    }

    public function test_ticket_show_renders_with_attachments(): void
    {
        $ticket = Ticket::factory()->create();
        TicketAttachment::factory()->for($ticket)->create(['file_name' => 'crash.log']);

        $this->get(route('dashboard.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('crash.log');
    }

    public function test_updating_ticket_status_persists_and_redirects(): void
    {
        $ticket = Ticket::factory()->create(['status' => 'open']);

        $response = $this->patch(route('dashboard.tickets.update', $ticket), ['status' => 'resolved']);

        $response->assertRedirect(route('dashboard.tickets.show', $ticket));
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'status' => 'resolved']);
    }

    public function test_attachment_download_streams_the_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ticket-attachments/x/y.png', 'fake-content');

        $ticket = Ticket::factory()->create();
        $attachment = TicketAttachment::factory()->for($ticket)->create([
            'file_path' => 'ticket-attachments/x/y.png',
            'file_name' => 'y.png',
        ]);

        $this->get(route('dashboard.tickets.attachments.download', [$ticket, $attachment]))->assertOk();
    }

    public function test_attachment_download_404s_when_attachment_belongs_to_a_different_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $otherTicket = Ticket::factory()->create();
        $attachment = TicketAttachment::factory()->for($otherTicket)->create();

        $this->get(route('dashboard.tickets.attachments.download', [$ticket, $attachment]))->assertNotFound();
    }
}
