<?php

namespace App\Console\Commands;

use App\Models\CountryTrafficSummary;
use App\Models\Event;
use App\Models\HourlyTrafficSummary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolls one day of raw `events` into `hourly_traffic_summary` and
 * `country_traffic_summary` so dashboard analytics (Fase 3.3+) never has to
 * scan the raw, ever-growing events table directly (Fase 3.2).
 *
 * Runs daily against yesterday by default, once that day's data is settled.
 * Upserting on the summary tables' unique keys makes a re-run (e.g. a manual
 * backfill) safe to repeat instead of doubling counts.
 */
#[Signature('app:generate-traffic-summaries {--date= : Date to summarize (Y-m-d). Defaults to yesterday.}')]
#[Description('Generate hourly and country traffic summaries from raw telemetry events')]
class GenerateTrafficSummaries extends Command
{
    public function handle(): int
    {
        $dateOption = $this->option('date');
        $date = $dateOption ? Carbon::parse($dateOption)->startOfDay() : now()->subDay()->startOfDay();

        $this->summarizeHourlyTraffic($date);
        $this->summarizeCountryTraffic($date);

        $this->info("Traffic summaries generated for {$date->toDateString()}.");

        return self::SUCCESS;
    }

    private function summarizeHourlyTraffic(Carbon $date): void
    {
        $counts = Event::query()
            ->selectRaw("{$this->hourExpression()} as hour, COUNT(*) as total")
            ->whereBetween('occurred_at', [$date, $date->copy()->endOfDay()])
            ->groupBy('hour')
            ->pluck('total', 'hour');

        for ($hour = 0; $hour < 24; $hour++) {
            HourlyTrafficSummary::updateOrCreate(
                ['date' => $date->toDateString(), 'hour' => $hour],
                ['event_count' => (int) ($counts[$hour] ?? 0)],
            );
        }
    }

    private function summarizeCountryTraffic(Carbon $date): void
    {
        $counts = Event::query()
            ->selectRaw('country_code, COUNT(DISTINCT device_id) as total')
            ->whereBetween('occurred_at', [$date, $date->copy()->endOfDay()])
            ->whereNotNull('country_code')
            ->groupBy('country_code')
            ->get();

        foreach ($counts as $row) {
            CountryTrafficSummary::updateOrCreate(
                ['date' => $date->toDateString(), 'country_code' => $row->country_code],
                ['device_count' => (int) $row->total],
            );
        }
    }

    /**
     * HOUR() is MySQL/MariaDB-only — sqlite (used for local dev/tests, per
     * CLAUDE.md) needs strftime instead, so the extraction expression is
     * picked per-connection to keep this command portable across both.
     */
    private function hourExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', occurred_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(HOUR FROM occurred_at)',
            default => 'HOUR(occurred_at)',
        };
    }
}
