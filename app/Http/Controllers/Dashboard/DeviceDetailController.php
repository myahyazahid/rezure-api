<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\CountryNameResolver;
use App\Services\DashboardMetricsService;
use Illuminate\View\View;

class DeviceDetailController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsService $metrics,
        private readonly CountryNameResolver $countryNames,
    ) {}

    public function __invoke(Device $device): View
    {
        $recentSessions = $this->metrics->deviceRecentSessions($device);
        $latestCountryCode = $recentSessions->first()?->country_code;

        return view('dashboard.device-detail', [
            'device' => $device,
            'usage' => $this->metrics->deviceUsage($device),
            'recentSessions' => $recentSessions,
            'services' => $this->metrics->deviceServiceUsage($device),
            'stack' => $this->metrics->deviceLatestStack($device),
            'deviceErrors' => $this->metrics->deviceErrors($device),
            'country' => $latestCountryCode ? $this->countryNames->name($latestCountryCode) : null,
        ]);
    }
}
