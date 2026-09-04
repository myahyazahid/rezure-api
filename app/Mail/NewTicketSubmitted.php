<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued (ShouldQueue) so a slow/unavailable mail transport never adds
 * latency to ProcessTicketSubmissionJob — sending is itself dispatched as a
 * follow-up job rather than blocking the job that created the ticket.
 */
class NewTicketSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Ticket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "New {$this->ticket->category} ticket: {$this->ticket->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tickets.new-submission',
            with: [
                'ticket' => $this->ticket,
                'dashboardUrl' => route('dashboard.tickets.show', $this->ticket),
            ],
        );
    }
}
