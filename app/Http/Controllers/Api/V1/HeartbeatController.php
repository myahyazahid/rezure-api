<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Telemetry\HeartbeatRequest;
use App\Jobs\ProcessHeartbeatJob;
use Illuminate\Http\JsonResponse;

class HeartbeatController extends Controller
{
    /**
     * Validates fast and hands off to the queue — ingestion never does
     * writes in the request cycle (CLAUDE.md: never block on the client).
     * The request IP rides along only to be resolved to a country_code in
     * the job — it's never stored as-is (Fase 3.1).
     */
    public function __invoke(HeartbeatRequest $request): JsonResponse
    {
        ProcessHeartbeatJob::dispatch([...$request->validated(), 'ip' => $request->ip()]);

        return response()->json(['status' => 'queued'], 202);
    }
}
