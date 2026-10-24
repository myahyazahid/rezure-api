<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        // Ingestion endpoints are public — any installed client can reach them —
        // so the whole API group is throttled. The 'api' limiter is defined in
        // AppServiceProvider.
        $middleware->throttleApi();
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Rolls up yesterday's raw events into the traffic summary tables
        // (Fase 3.2) once that day's data is settled.
        $schedule->command('app:generate-traffic-summaries')->dailyAt('00:10');

        // Website analytics (v4). Summaries run after midnight in the
        // display timezone they bucket by, for yesterday and the day before
        // (late hits); raw web_* rows past retention are pruned after.
        $timezone = config('app.display_timezone');
        $schedule->command('app:generate-web-summaries')->dailyAt('00:20')->timezone($timezone)->withoutOverlapping();
        $schedule->command('model:prune')->dailyAt('02:00')->timezone($timezone);
        $schedule->command('app:sync-github-download-counts')->dailyAt('23:50')->timezone($timezone);
        $schedule->command('app:attribute-web-installs')->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
