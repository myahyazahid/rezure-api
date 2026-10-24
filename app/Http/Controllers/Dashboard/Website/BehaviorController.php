<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\BehaviorReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BehaviorController extends Controller
{
    public function __construct(private readonly BehaviorReport $report) {}

    public function __invoke(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);

        return view('dashboard.website.behavior', [
            'filters' => $filters,
            'report' => $this->report->build($filters),
        ]);
    }
}
