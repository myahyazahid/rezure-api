<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetricsService;
use Illuminate\Http\JsonResponse;

class PublicStatsController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    /**
     * Aggregate-only, no auth (Fase 3.7) — see
     * DashboardMetricsService::publicAggregateStats() for what's
     * deliberately excluded and why.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json($this->metrics->publicAggregateStats());
    }
}
