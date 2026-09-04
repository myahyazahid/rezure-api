<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketsExportController extends Controller
{
    public function __invoke(Request $request, TicketsController $tickets): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($request, $tickets): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['title', 'description', 'category', 'status', 'device', 'app_version', 'os_version', 'created_at']);

            $tickets->filteredQuery($request)
                ->orderByDesc('created_at')
                ->cursor()
                ->each(function ($ticket) use ($handle): void {
                    fputcsv($handle, [
                        $ticket->title,
                        $ticket->description,
                        $ticket->category,
                        $ticket->status,
                        $ticket->device?->short_id,
                        $ticket->app_version,
                        $ticket->os_version,
                        $ticket->created_at?->toIso8601String(),
                    ]);
                });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="rezure-tickets-'.now()->format('Y-m-d').'.csv"');

        return $response;
    }
}
