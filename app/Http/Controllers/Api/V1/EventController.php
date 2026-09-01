<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Telemetry\EventRequest;
use App\Jobs\ProcessEventJob;
use Illuminate\Http\JsonResponse;

class EventController extends Controller
{
    /**
     * Validates fast and hands off to the queue — ingestion never does
     * writes in the request cycle (CLAUDE.md: never block on the client).
     */
    public function __invoke(EventRequest $request): JsonResponse
    {
        ProcessEventJob::dispatch($request->validated());

        return response()->json(['status' => 'queued'], 202);
    }
}
