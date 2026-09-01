<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetricsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DevicesController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function __invoke(Request $request): View
    {
        $versionFilter = $request->query('version');

        return view('dashboard.devices', [
            'devices' => $this->metrics->paginatedDevices($versionFilter ?: null),
            'knownVersions' => $this->metrics->knownVersions(),
            'versionFilter' => $versionFilter,
        ]);
    }
}
