<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\ChangelogController;
use App\Http\Controllers\Dashboard\DevicesController;
use App\Http\Controllers\Dashboard\DevicesExportController;
use App\Http\Controllers\Dashboard\ErrorsController;
use App\Http\Controllers\Dashboard\FeaturesController;
use App\Http\Controllers\Dashboard\OverviewController;
use App\Http\Controllers\Dashboard\ReleasesController;
use App\Http\Controllers\Dashboard\TicketAttachmentDownloadController;
use App\Http\Controllers\Dashboard\TicketsController;
use App\Http\Controllers\Dashboard\TicketsExportController;
use App\Http\Controllers\Dashboard\VersionsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Rezure Telemetry Dashboard
|--------------------------------------------------------------------------
|
| Internal analytics for maintainers — auth-gated (see CLAUDE.md and
| docs/releases.md). Most of this is read-only; publishing a release is
| the one write path and sits behind the same guard as everything else
| here.
|
*/

Route::prefix('dashboard')->name('dashboard.')->middleware('auth')->group(function (): void {
    Route::get('/', OverviewController::class)->name('overview');
    Route::get('/versions', VersionsController::class)->name('versions');
    Route::get('/features', FeaturesController::class)->name('features');
    Route::get('/errors', ErrorsController::class)->name('errors');
    Route::get('/devices', DevicesController::class)->name('devices');
    Route::get('/devices/export', DevicesExportController::class)->name('devices.export');

    Route::get('/releases', [ReleasesController::class, 'index'])->name('releases');
    Route::post('/releases', [ReleasesController::class, 'store'])->name('releases.store');

    Route::get('/tickets', [TicketsController::class, 'index'])->name('tickets');
    Route::get('/tickets/export', TicketsExportController::class)->name('tickets.export');
    Route::get('/tickets/{ticket}', [TicketsController::class, 'show'])->name('tickets.show');
    Route::patch('/tickets/{ticket}', [TicketsController::class, 'update'])->name('tickets.update');
    Route::get('/tickets/{ticket}/attachments/{attachment}/download', TicketAttachmentDownloadController::class)
        ->name('tickets.attachments.download');

    Route::get('/changelog', [ChangelogController::class, 'index'])->name('changelog');
    Route::post('/changelog', [ChangelogController::class, 'store'])->name('changelog.store');
    Route::put('/changelog/{changelog}', [ChangelogController::class, 'update'])->name('changelog.update');
    Route::delete('/changelog/{changelog}', [ChangelogController::class, 'destroy'])->name('changelog.destroy');
});
