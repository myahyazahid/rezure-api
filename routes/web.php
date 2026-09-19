<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\BehaviorController;
use App\Http\Controllers\Dashboard\BlogController;
use App\Http\Controllers\Dashboard\ChangelogController;
use App\Http\Controllers\Dashboard\DevicesController;
use App\Http\Controllers\Dashboard\DevicesExportController;
use App\Http\Controllers\Dashboard\DonateController;
use App\Http\Controllers\Dashboard\ErrorsController;
use App\Http\Controllers\Dashboard\FeaturesController;
use App\Http\Controllers\Dashboard\FunnelController;
use App\Http\Controllers\Dashboard\GeographyController;
use App\Http\Controllers\Dashboard\OverviewController;
use App\Http\Controllers\Dashboard\ReleasesController;
use App\Http\Controllers\Dashboard\TechnicalController;
use App\Http\Controllers\Dashboard\TicketAttachmentDownloadController;
use App\Http\Controllers\Dashboard\TicketsController;
use App\Http\Controllers\Dashboard\TicketsExportController;
use App\Http\Controllers\Dashboard\TrafficController;
use App\Http\Controllers\Dashboard\VersionsController;
use App\Http\Controllers\Dashboard\WhatsAppController;
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
| Internal analytics for maintainers â€” auth-gated (see CLAUDE.md and
| docs/releases.md). Most of this is read-only; publishing a release is
| the one write path and sits behind the same guard as everything else
| here.
|
*/

Route::prefix('dashboard')->name('dashboard.')->middleware('auth')->group(function (): void {
    Route::get('/', OverviewController::class)->name('overview');
    Route::get('/whatsapp', [WhatsAppController::class, 'index'])->name('whatsapp');
    Route::get('/whatsapp/devices', [WhatsAppController::class, 'devices'])->name('whatsapp.devices');
    Route::get('/whatsapp/qr', [WhatsAppController::class, 'qr'])->name('whatsapp.qr');
    Route::post('/whatsapp/pairing-code', [WhatsAppController::class, 'pairingCode'])->name('whatsapp.pairing-code');
    Route::get('/whatsapp/qr-image', [WhatsAppController::class, 'qrImage'])->name('whatsapp.qr-image');
    Route::get('/whatsapp/status', [WhatsAppController::class, 'status'])->name('whatsapp.status');
    Route::delete('/whatsapp/devices/{id}', [WhatsAppController::class, 'logout'])->name('whatsapp.devices.logout');
    Route::post('/whatsapp/devices/{id}/reconnect', [WhatsAppController::class, 'reconnect'])->name('whatsapp.devices.reconnect');
    Route::get('/versions', VersionsController::class)->name('versions');
    Route::get('/features', FeaturesController::class)->name('features');
    Route::get('/errors', ErrorsController::class)->name('errors');
    Route::get('/devices', DevicesController::class)->name('devices');
    Route::get('/devices/export', DevicesExportController::class)->name('devices.export');

    Route::get('/traffic', TrafficController::class)->name('traffic');
    Route::get('/geography', GeographyController::class)->name('geography');
    Route::get('/behavior', BehaviorController::class)->name('behavior');
    Route::get('/technical', TechnicalController::class)->name('technical');
    Route::get('/funnel', FunnelController::class)->name('funnel');

    Route::get('/blogs', [BlogController::class, 'index'])->name('blogs.index');
    Route::get('/blogs/create', [BlogController::class, 'create'])->name('blogs.create');
    Route::post('/blogs', [BlogController::class, 'store'])->name('blogs.store');
    Route::get('/blogs/{blog}/edit', [BlogController::class, 'edit'])->name('blogs.edit');
    Route::put('/blogs/{blog}', [BlogController::class, 'update'])->name('blogs.update');
    Route::delete('/blogs/{blog}', [BlogController::class, 'destroy'])->name('blogs.destroy');
    Route::patch('/blogs/{blog}/toggle-publish', [BlogController::class, 'togglePublish'])->name('blogs.toggle-publish');
    Route::get('/blogs/{blog}/logs', [BlogController::class, 'logs'])->withTrashed()->name('blogs.logs');
    Route::post('/blogs/{blog}/retrigger', [BlogController::class, 'retrigger'])->withTrashed()->name('blogs.retrigger');
    Route::patch('/blogs/{blog}/restore', [BlogController::class, 'restore'])->withTrashed()->name('blogs.restore');
    Route::delete('/blogs/{blog}/force-delete', [BlogController::class, 'forceDelete'])->withTrashed()->name('blogs.force-delete');

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

    Route::get('/donate', [DonateController::class, 'index'])->name('donate');
    Route::put('/donate/message', [DonateController::class, 'updateMessage'])->name('donate.message.update');
    Route::get('/donate/create', [DonateController::class, 'create'])->name('donate.create');
    Route::post('/donate', [DonateController::class, 'store'])->name('donate.store');
    Route::get('/donate/{donateMethod}/edit', [DonateController::class, 'edit'])->name('donate.edit');
    Route::put('/donate/{donateMethod}', [DonateController::class, 'update'])->name('donate.update');
    Route::delete('/donate/{donateMethod}', [DonateController::class, 'destroy'])->name('donate.destroy');
});
