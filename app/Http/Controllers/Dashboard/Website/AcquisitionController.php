<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\AcquisitionReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcquisitionController extends Controller
{
    public function __construct(private readonly AcquisitionReport $report) {}

    public function __invoke(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);

        return view('dashboard.website.acquisition', [
            'filters' => $filters,
            'report' => $this->report->build($filters),
        ]);
    }
}
