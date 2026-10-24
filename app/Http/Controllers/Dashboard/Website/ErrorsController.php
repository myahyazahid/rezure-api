<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\PerformanceReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ErrorsController extends Controller
{
    public function __construct(private readonly PerformanceReport $report) {}

    public function index(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);

        return view('dashboard.website.errors', [
            'filters' => $filters,
            // Not `errors`: Laravel shares that name with every view (validation).
            'errorGroups' => $this->report->errors($filters),
        ]);
    }

    public function show(Request $request, string $fingerprint): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);
        $detail = $this->report->errorDetail($filters, $fingerprint);

        abort_if($detail['sample'] === null, 404);

        return view('dashboard.website.error-detail', [
            'filters' => $filters,
            'detail' => $detail,
        ]);
    }
}
