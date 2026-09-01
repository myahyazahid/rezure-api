<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketAttachmentDownloadController extends Controller
{
    /**
     * Route model binding alone doesn't scope the attachment to its ticket —
     * without this check, swapping the attachment id in the URL would let
     * one ticket's attachment be downloaded through another ticket's page.
     */
    public function __invoke(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->ticket_id === $ticket->id, 404);

        return Storage::disk('local')->download($attachment->file_path, $attachment->file_name);
    }
}
