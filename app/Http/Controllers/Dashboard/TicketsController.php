<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateTicketStatusRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketsController extends Controller
{
    public function index(Request $request): View
    {
        $tickets = Ticket::query()
            ->with('device')
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.tickets.index', [
            'tickets' => $tickets,
            'statusFilter' => $request->query('status'),
            'categoryFilter' => $request->query('category'),
        ]);
    }

    public function show(Ticket $ticket): View
    {
        return view('dashboard.tickets.show', [
            'ticket' => $ticket->load('attachments', 'device'),
        ]);
    }

    public function update(UpdateTicketStatusRequest $request, Ticket $ticket): RedirectResponse
    {
        $ticket->update($request->validated());

        return redirect()->route('dashboard.tickets.show', $ticket)->with('status', 'Ticket updated.');
    }
}
