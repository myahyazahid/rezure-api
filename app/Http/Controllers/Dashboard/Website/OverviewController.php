<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\OverviewReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function __construct(private readonly OverviewReport $report) {}

    public function __invoke(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);

        return view('dashboard.website.overview', [
            'filters' => $filters,
            'report' => $this->report->build($filters),
            'activeVisitors' => $this->report->activeVisitors(),
        ]);
    }
}
