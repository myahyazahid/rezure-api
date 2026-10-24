<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\RealtimeReport;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class RealtimeController extends Controller
{
    public function __construct(private readonly RealtimeReport $report) {}

    public function index(): View
    {
        return view('dashboard.website.realtime', ['snapshot' => $this->report->snapshot()]);
    }

    /**
     * Polled every 10 seconds by the Realtime page.
     */
    public function data(): JsonResponse
    {
        return response()->json($this->report->snapshot());
    }
}
