<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardMetricsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DevicesExportController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function __invoke(Request $request): StreamedResponse
    {
        $versionFilter = $request->query('version');

        $response = new StreamedResponse(function () use ($versionFilter): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['device_id', 'app_version', 'os', 'os_version', 'first_seen_at', 'last_seen_at']);

            $this->metrics->devicesQuery($versionFilter ?: null)
                ->cursor()
                ->each(function ($device) use ($handle): void {
                    fputcsv($handle, [
                        $device->device_id,
                        $device->app_version,
                        $device->os,
                        $device->os_version,
                        $device->first_seen_at?->toIso8601String(),
                        $device->last_seen_at?->toIso8601String(),
                    ]);
                });

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="rezure-devices-'.now()->format('Y-m-d').'.csv"');

        return $response;
    }
}
