<?php

use App\Http\Controllers\Dashboard\DevicesController;
use App\Http\Controllers\Dashboard\DevicesExportController;
use App\Http\Controllers\Dashboard\ErrorsController;
use App\Http\Controllers\Dashboard\FeaturesController;
use App\Http\Controllers\Dashboard\OverviewController;
use App\Http\Controllers\Dashboard\ReleasesController;
use App\Http\Controllers\Dashboard\VersionsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*
|--------------------------------------------------------------------------
| Rezure Telemetry Dashboard
|--------------------------------------------------------------------------
|
| Internal analytics for maintainers — no public auth guard yet (see
| CLAUDE.md and docs/releases.md). Most of this is read-only; publishing a
| release is the one write path, and it's just as open as everything else
| here until real auth lands.
|
*/

Route::prefix('dashboard')->name('dashboard.')->group(function (): void {
    Route::get('/', OverviewController::class)->name('overview');
    Route::get('/versions', VersionsController::class)->name('versions');
    Route::get('/features', FeaturesController::class)->name('features');
    Route::get('/errors', ErrorsController::class)->name('errors');
    Route::get('/devices', DevicesController::class)->name('devices');
    Route::get('/devices/export', DevicesExportController::class)->name('devices.export');

    Route::get('/releases', [ReleasesController::class, 'index'])->name('releases');
    Route::post('/releases', [ReleasesController::class, 'store'])->name('releases.store');
});
