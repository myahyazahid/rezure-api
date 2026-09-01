<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\TicketHistoryRequest;
use App\Http\Requests\Support\TicketRequest;
use App\Jobs\ProcessTicketSubmissionJob;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class TicketController extends Controller
{
    /**
     * Files must hit disk synchronously — the UploadedFile temp file won't
     * survive into a queued job — but no DB write happens here, keeping
     * ingestion's "never write synchronously" rule intact (CLAUDE.md).
     */
    public function store(TicketRequest $request): JsonResponse
    {
        $data = $request->validated();

        $attachments = collect($request->file('attachments', []))
            ->map(fn (UploadedFile $file): array => [
                'file_path' => $file->store("ticket-attachments/{$data['client_ticket_id']}", 'local'),
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ])
            ->all();

        ProcessTicketSubmissionJob::dispatch([
            ...Arr::except($data, 'attachments'),
            'attachments' => $attachments,
        ]);

        return response()->json(['status' => 'queued'], 202);
    }

    /**
     * Read-only history for a single device — never queued, since reads
     * don't need the "never write synchronously" treatment.
     */
    public function index(TicketHistoryRequest $request): JsonResponse
    {
        $tickets = Ticket::query()
            ->whereHas('device', fn ($query) => $query->where('device_id', $request->validated('device_id')))
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['category', 'title', 'status', 'created_at']);

        return response()->json($tickets);
    }
}
