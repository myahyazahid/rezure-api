<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Dashboard\BehaviorController;
use App\Http\Controllers\Dashboard\BlogController;
use App\Http\Controllers\Dashboard\BlogImageController;
use App\Http\Controllers\Dashboard\ChangelogController;
use App\Http\Controllers\Dashboard\DeviceDetailController;
use App\Http\Controllers\Dashboard\DevicesController;
use App\Http\Controllers\Dashboard\DevicesExportController;
use App\Http\Controllers\Dashboard\DonateController;
use App\Http\Controllers\Dashboard\ErrorsController;
use App\Http\Controllers\Dashboard\FeaturesController;
use App\Http\Controllers\Dashboard\FunnelController;
use App\Http\Controllers\Dashboard\GeographyController;
use App\Http\Controllers\Dashboard\OverviewController;
use App\Http\Controllers\Dashboard\ReleasesController;
use App\Http\Controllers\Dashboard\StickerController;
use App\Http\Controllers\Dashboard\TechnicalController;
use App\Http\Controllers\Dashboard\TicketAttachmentDownloadController;
use App\Http\Controllers\Dashboard\TicketsController;
use App\Http\Controllers\Dashboard\TicketsExportController;
use App\Http\Controllers\Dashboard\TrafficController;
use App\Http\Controllers\Dashboard\VersionsController;
use App\Http\Controllers\Dashboard\Website;
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
    Route::get('/devices/{device}', DeviceDetailController::class)->whereNumber('device')->name('devices.show');

    Route::get('/traffic', TrafficController::class)->name('traffic');
    Route::get('/geography', GeographyController::class)->name('geography');
    Route::get('/behavior', BehaviorController::class)->name('behavior');
    Route::get('/technical', TechnicalController::class)->name('technical');
    Route::get('/funnel', FunnelController::class)->name('funnel');

    // v4 website analytics — the public website's visitors, not desktop
    // devices. Every page reads the same period/compare/filter query string
    // (WebAnalyticsFilters).
    Route::prefix('website')->name('website.')->group(function (): void {
        Route::get('/', Website\OverviewController::class)->name('overview');
        Route::get('/realtime', [Website\RealtimeController::class, 'index'])->name('realtime');
        Route::get('/realtime/data', [Website\RealtimeController::class, 'data'])->name('realtime.data');
        Route::get('/pages', [Website\PagesController::class, 'index'])->name('pages');
        Route::get('/pages/detail', [Website\PagesController::class, 'show'])->name('pages.show');
        Route::get('/content', Website\ContentController::class)->name('content');
        Route::get('/acquisition', Website\AcquisitionController::class)->name('acquisition');
        Route::get('/audience', Website\AudienceController::class)->name('audience');
        Route::get('/visitors', [Website\VisitorsController::class, 'index'])->name('visitors');
        Route::get('/visitors/{webVisitor}', [Website\VisitorsController::class, 'show'])->whereNumber('webVisitor')->name('visitors.show');
        Route::get('/sessions/{webSession}', Website\SessionController::class)->whereNumber('webSession')->name('sessions.show');
        Route::get('/technology', Website\TechnologyController::class)->name('technology');
        Route::get('/behavior', Website\BehaviorController::class)->name('behavior');
        Route::get('/heatmap', Website\HeatmapController::class)->name('heatmap');
        Route::get('/events', Website\EventsController::class)->name('events');
        Route::get('/goals', [Website\GoalsController::class, 'index'])->name('goals');
        Route::post('/goals', [Website\GoalsController::class, 'store'])->name('goals.store');
        Route::put('/goals/{webGoal}', [Website\GoalsController::class, 'update'])->name('goals.update');
        Route::delete('/goals/{webGoal}', [Website\GoalsController::class, 'destroy'])->name('goals.destroy');
        Route::get('/funnels', [Website\FunnelsController::class, 'index'])->name('funnels');
        Route::post('/funnels', [Website\FunnelsController::class, 'store'])->name('funnels.store');
        Route::get('/funnels/{webFunnel}', [Website\FunnelsController::class, 'show'])->whereNumber('webFunnel')->name('funnels.show');
        Route::delete('/funnels/{webFunnel}', [Website\FunnelsController::class, 'destroy'])->name('funnels.destroy');
        Route::get('/performance', Website\PerformanceController::class)->name('performance');
        Route::get('/errors', [Website\ErrorsController::class, 'index'])->name('errors');
        Route::get('/errors/{fingerprint}', [Website\ErrorsController::class, 'show'])->where('fingerprint', '[a-f0-9]{40}')->name('errors.show');
        Route::get('/conversion', Website\ConversionController::class)->name('conversion');
        Route::get('/export', Website\ExportController::class)->name('export');
    });

    Route::get('/blogs', [BlogController::class, 'index'])->name('blogs.index');
    Route::get('/blogs/create', [BlogController::class, 'create'])->name('blogs.create');
    Route::post('/blogs', [BlogController::class, 'store'])->name('blogs.store');
    Route::post('/blogs/images', [BlogImageController::class, 'store'])->name('blogs.images.store');
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
    Route::put('/releases/upgrade-notice', [ReleasesController::class, 'updateUpgradeNotice'])->name('releases.upgrade-notice.update');

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
    // Declared before the `{donateMethod}` routes below, or `qris` would be
    // taken for a method id.
    Route::get('/donate/qris/preview', [DonateController::class, 'previewQris'])->name('donate.qris.preview');
    Route::put('/donate/qris', [DonateController::class, 'updateQris'])->name('donate.qris.update');
    Route::delete('/donate/qris', [DonateController::class, 'destroyQris'])->name('donate.qris.destroy');
    Route::get('/donate/create', [DonateController::class, 'create'])->name('donate.create');
    Route::post('/donate', [DonateController::class, 'store'])->name('donate.store');
    Route::get('/donate/{donateMethod}/edit', [DonateController::class, 'edit'])->name('donate.edit');
    Route::get('/donate/{donateMethod}/icon', [DonateController::class, 'previewIcon'])->name('donate.icon.preview');
    Route::put('/donate/{donateMethod}', [DonateController::class, 'update'])->name('donate.update');
    Route::delete('/donate/{donateMethod}', [DonateController::class, 'destroy'])->name('donate.destroy');

    Route::get('/stickers', [StickerController::class, 'index'])->name('stickers.index');
    Route::get('/stickers/create', [StickerController::class, 'create'])->name('stickers.create');
    Route::post('/stickers', [StickerController::class, 'store'])->name('stickers.store');
    Route::get('/stickers/{sticker}/edit', [StickerController::class, 'edit'])->name('stickers.edit');
    Route::get('/stickers/{sticker}/preview', [StickerController::class, 'preview'])->name('stickers.preview');
    Route::put('/stickers/{sticker}', [StickerController::class, 'update'])->name('stickers.update');
    Route::patch('/stickers/{sticker}/toggle-publish', [StickerController::class, 'togglePublish'])->name('stickers.toggle-publish');
    Route::delete('/stickers/{sticker}', [StickerController::class, 'destroy'])->name('stickers.destroy');
});
