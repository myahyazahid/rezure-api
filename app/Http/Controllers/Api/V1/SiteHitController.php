<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\SiteHitRequest;
use App\Jobs\ProcessSiteHitBatchJob;
use Illuminate\Http\JsonResponse;

class SiteHitController extends Controller
{
    /**
     * Website analytics ingestion (v4). Same shape as the desktop telemetry
     * endpoints: validate fast, queue, 202. The request context (IP, user
     * agent, ...) rides along because the job, not the tracker, is the
     * source of truth for it — website analytics keeps the raw IP (docs/v4).
     */
    public function __invoke(SiteHitRequest $request): JsonResponse
    {
        ProcessSiteHitBatchJob::dispatch($request->validated(), [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'accept_language' => $request->header('Accept-Language'),
            'origin' => $request->header('Origin'),
            'received_at' => now()->format('Y-m-d\TH:i:s.vP'),
        ]);

        return response()->json(['status' => 'queued'], 202);
    }
}
