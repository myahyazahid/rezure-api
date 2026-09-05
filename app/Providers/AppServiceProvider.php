<?php

namespace App\Providers;

use App\Services\DashboardMetricsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        View::composer('components.dashboard-layout', function ($view): void {
            $view->with('navBadges', app(DashboardMetricsService::class)->navigationBadges());
        });
    }

    /**
     * Rate limits for the client-facing API.
     *
     * Keyed by device first and IP only as a fallback: several Rezure
     * installations can sit behind one office NAT, so an IP-only limit would
     * let one busy machine starve its neighbours. device_id normally arrives
     * in the JSON body (it's part of every telemetry payload); the header is
     * an optional fast-path for callers that want to be bucketed before
     * validation runs. A device that has identified itself neither way gets
     * the IP bucket, which is the abuse case the limit actually exists for.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $deviceId = $request->header('X-Rezure-Device-Id') ?? $request->input('device_id');

            return $deviceId
                ? Limit::perMinute(60)->by('device:'.$deviceId)
                : Limit::perMinute(30)->by('ip:'.$request->ip());
        });

        $this->configureSupportRateLimiting();
        $this->configurePublicStatsRateLimiting();
    }

    /**
     * The public stats endpoint (Fase 3.7) has no device_id at all — it's
     * meant for the public website, not the desktop client — so it's keyed
     * purely by IP and capped tightly; it's read-only aggregate data, but
     * there's no reason to let it be hammered.
     */
    protected function configurePublicStatsRateLimiting(): void
    {
        RateLimiter::for('public-stats', fn (Request $request): Limit => Limit::perMinute(20)->by('ip:'.$request->ip()));
    }

    /**
     * Stacks on top of the global 'api' limiter above — ticket spam
     * (human-authored content, multipart uploads) is a distinct abuse
     * vector from heartbeat/event spam and warrants its own, much
     * stricter ceiling.
     */
    protected function configureSupportRateLimiting(): void
    {
        RateLimiter::for('support', function (Request $request): Limit {
            $deviceId = $request->header('X-Rezure-Device-Id') ?? $request->input('device_id');

            return $deviceId
                ? Limit::perHour(10)->by('device:'.$deviceId)
                : Limit::perHour(5)->by('ip:'.$request->ip());
        });
    }
}
