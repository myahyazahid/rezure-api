<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\ConversionReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConversionController extends Controller
{
    public function __construct(private readonly ConversionReport $report) {}

    public function __invoke(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);

        return view('dashboard.website.conversion', [
            'filters' => $filters,
            'report' => $this->report->crossProduct($filters),
        ]);
    }
}
