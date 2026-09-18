<?php

use App\Http\Controllers\Api\V1\BlogController;
use App\Http\Controllers\Api\V1\ChangelogController;
use App\Http\Controllers\Api\V1\DonateController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HeartbeatController;
use App\Http\Controllers\Api\V1\PublicStatsController;
use App\Http\Controllers\Api\V1\TicketController;
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

    Route::get('/blogs', [BlogController::class, 'index'])->name('blogs.index');
    Route::get('/blogs/{slug}', [BlogController::class, 'show'])->name('blogs.show');

    Route::get('/changelog', ChangelogController::class)->name('changelog');

    Route::prefix('support')->name('support.')->group(function (): void {
        Route::post('/tickets', [TicketController::class, 'store'])
            ->middleware('throttle:support')
            ->name('tickets.store');
        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');

        // No device_id, no auth — same "small, non-sensitive public read"
        // framing as /changelog, so it rides the shared 'api' limiter only.
        Route::get('/donate', DonateController::class)->name('donate');
    });

    // Fase 3.7: aggregate-only, no device auth — meant for the public
    // website, not the desktop client. See DashboardMetricsService::
    // publicAggregateStats() for what's deliberately excluded.
    Route::prefix('stats')->name('stats.')->group(function (): void {
        Route::get('/public', PublicStatsController::class)
            ->middleware('throttle:public-stats')
            ->name('public');
    });
});

Route::get('/blogs', [BlogController::class, 'index'])->name('api.blogs');
Route::get('/blogs/{slug}', [BlogController::class, 'show'])->name('api.blogs.show');
