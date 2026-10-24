<?php

namespace App\Http\Controllers\Dashboard\Website;

use App\Http\Controllers\Controller;
use App\Services\WebAnalytics\Reports\BehaviorReport;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Support\ViewportBucket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HeatmapController extends Controller
{
    public function __construct(private readonly BehaviorReport $report) {}

    public function __invoke(Request $request): View
    {
        $filters = WebAnalyticsFilters::fromRequest($request);
        $path = '/'.ltrim((string) $request->query('path', '/'), '/');
        $bucket = in_array($request->query('viewport'), ViewportBucket::all(), true) ? (string) $request->query('viewport') : 'desktop';
        $host = config('web-analytics.allowed_hosts')[0] ?? 'rezure.redscale.my.id';

        return view('dashboard.website.heatmap', [
            'filters' => $filters,
            'path' => $path,
            'bucket' => $bucket,
            'buckets' => ViewportBucket::all(),
            'pageUrl' => 'https://'.$host.$path,
            'heatmap' => $this->report->heatmap($filters, $path, $bucket),
        ]);
    }
}
