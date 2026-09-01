<?php

use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HeartbeatController;
use App\Http\Controllers\Api\V1\VersionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rezure Telemetry API
|--------------------------------------------------------------------------
|
| Versioned under /api/v1 because desktop clients ship on their own release
| cadence. An installed Rezure client keeps calling the prefix it was built
| against, so a version group stays reachable for as long as clients on that
| version are still in the wild — never repurpose one, add a new group.
|
| See docs/api.md for the full request/response contract.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/ping', function (): array {
        return [
            'status' => 'ok',
            'time' => now()->toIso8601String(),
        ];
    })->name('ping');

    Route::prefix('telemetry')->name('telemetry.')->group(function (): void {
        Route::post('/heartbeat', HeartbeatController::class)->name('heartbeat');
        Route::post('/event', EventController::class)->name('event');
    });

    Route::get('/version/latest', VersionController::class)->name('version.latest');
});
