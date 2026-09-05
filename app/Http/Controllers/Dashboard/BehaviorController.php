<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BehaviorController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function __invoke(Request $request): View
    {
        $period = $this->resolvePeriod($request);

        return view('dashboard.behavior', [
            'period' => $period,
            'sessionLengths' => $this->metrics->sessionLengthDistribution($period),
            'newVsReturning' => $this->metrics->newVsReturningDevices($period),
            'cohorts' => $this->metrics->cohortRetention(6),
            'churn' => $this->metrics->churnSummary(30),
        ]);
    }

    private function resolvePeriod(Request $request): int
    {
        $period = (int) $request->query('period', 30);

        return in_array($period, [7, 30, 90], true) ? $period : 30;
    }
}
