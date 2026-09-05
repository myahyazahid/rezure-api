<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TechnicalController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function __invoke(Request $request): View
    {
        $period = $this->resolvePeriod($request);

        return view('dashboard.technical', [
            'period' => $period,
            'osVersions' => $this->metrics->osVersionBreakdown(),
            'stackCombos' => $this->metrics->topStackCombos($period),
        ]);
    }

    private function resolvePeriod(Request $request): int
    {
        $period = (int) $request->query('period', 30);

        return in_array($period, [7, 30, 90], true) ? $period : 30;
    }
}
