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
        // Ingestion endpoints are public — any installed client can reach them —
        // so the whole API group is throttled. The 'api' limiter is defined in
        // AppServiceProvider.
        $middleware->throttleApi();
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Rolls up yesterday's raw events into the traffic summary tables
        // (Fase 3.2) once that day's data is settled.
        $schedule->command('app:generate-traffic-summaries')->dailyAt('00:10');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
