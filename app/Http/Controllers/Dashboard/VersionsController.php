<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetricsService;
use Illuminate\View\View;

class VersionsController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function __invoke(): View
    {
        return view('dashboard.versions', [
            'versions' => $this->metrics->versionAdoption(),
        ]);
    }
}
