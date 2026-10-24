<?php

namespace App\Console\Commands;

use App\Models\WebDailyDimensionSummary;
use App\Models\WebDailyPageSummary;
use App\Models\WebDailySummary;
use App\Models\WebEvent;
use App\Models\WebHourlySummary;
use App\Services\WebAnalytics\LocalTime;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use App\Services\WebAnalytics\WebDimensions;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolls website analytics (web_* raw tables) into the web_*_summary tables
 * for whole display-timezone days (v4, Fase 4.3).
 *
 * By default it rolls up yesterday *and* the day before: hits can reach the
 * queue late (offline tabs, retried batches), so a day is rolled once when
 * it ends and once more the next night. Each date's rows are replaced, not
 * added to, so any re-run — including a --from/--to backfill — is safe.
 */
#[Signature('app:generate-web-summaries {--date= : One date to summarize (Y-m-d)} {--from= : Start of a date range to backfill (Y-m-d)} {--to= : End of the backfill range (Y-m-d)}')]
#[Description('Generate the daily website analytics summaries from raw web_* rows')]
class GenerateWebSummaries extends Command
{
    public function handle(WebAnalyticsQuery $query): int
    {
        foreach ($this->dates() as $date) {
            DB::transaction(fn () => $this->summarize($query, $date));

            $this->line("Website summaries generated for {$date->toDateString()}.");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<Carbon>
     */
    private function dates(): array
    {
        $timezone = config('app.display_timezone');

        if ($this->option('from') && $this->option('to')) {
            $from = Carbon::parse($this->option('from'), $timezone)->startOfDay();
            $to = Carbon::parse($this->option('to'), $timezone)->startOfDay();
            $dates = [];

            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                $dates[] = $date->copy();
            }

            return $dates;
        }

        if ($this->option('date')) {
            return [Carbon::parse($this->option('date'), $timezone)->startOfDay()];
        }

        $yesterday = now($timezone)->subDay()->startOfDay();

        return [$yesterday->copy()->subDay(), $yesterday];
    }

    private function summarize(WebAnalyticsQuery $query, Carbon $date): void
    {
        $filters = new WebAnalyticsFilters($date->copy()->startOfDay(), $date->copy()->endOfDay(), 'custom', 'none');
        $day = $date->toDateString();
        $now = now();

        $this->summarizeDay($query, $filters, $day);
        $this->summarizeHours($query, $filters, $day, $now);
        $this->summarizePages($query, $filters, $day, $now);
        $this->summarizeDimensions($query, $filters, $day, $now);
    }

    private function summarizeDay(WebAnalyticsQuery $query, WebAnalyticsFilters $filters, string $day): void
    {
        $sessions = $query->sessions($filters)
            ->toBase()
            ->selectRaw('COUNT(*) as sessions, COUNT(DISTINCT web_visitor_id) as visitors, COUNT(DISTINCT CASE WHEN is_new_visitor = 1 THEN web_visitor_id END) as new_visitors, SUM(is_engaged) as engaged_sessions, SUM(is_bounce) as bounces, SUM(duration_seconds) as total_duration, SUM(engaged_seconds) as total_engaged, SUM(has_conversion) as conversions')
            ->first();

        // A row is written even for a day without traffic — that is what
        // tells WebAnalyticsQuery the day is rolled up rather than missing.
        WebDailySummary::updateOrCreate(['date' => $day], [
            'visitors' => (int) $sessions->visitors,
            'new_visitors' => (int) $sessions->new_visitors,
            'sessions' => (int) $sessions->sessions,
            'engaged_sessions' => (int) $sessions->engaged_sessions,
            'bounces' => (int) $sessions->bounces,
            'pageviews' => $query->pageviews($filters)->count(),
            'total_duration_seconds' => (int) $sessions->total_duration,
            'total_engaged_seconds' => (int) $sessions->total_engaged,
            'downloads' => $query->events($filters, WebEvent::DOWNLOAD)->count(),
            'conversions' => (int) $sessions->conversions,
        ]);
    }

    private function summarizeHours(WebAnalyticsQuery $query, WebAnalyticsFilters $filters, string $day, Carbon $now): void
    {
        $sessionHour = LocalTime::hourOfDay('web_sessions.started_at');
        $pageviewHour = LocalTime::hourOfDay('web_pageviews.occurred_at');

        $sessions = $query->sessions($filters)
            ->toBase()
            ->selectRaw("{$sessionHour} as hour, COUNT(*) as sessions, COUNT(DISTINCT web_visitor_id) as visitors")
            ->groupByRaw($sessionHour)
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->hour);

        $pageviews = $query->pageviews($filters)
            ->toBase()
            ->selectRaw("{$pageviewHour} as hour, COUNT(*) as pageviews")
            ->groupByRaw($pageviewHour)
            ->pluck('pageviews', 'hour');

        WebHourlySummary::query()->where('date', $day)->delete();

        WebHourlySummary::insert(array_map(fn (int $hour): array => [
            'date' => $day,
            'hour' => $hour,
            'visitors' => (int) ($sessions[$hour]->visitors ?? 0),
            'sessions' => (int) ($sessions[$hour]->sessions ?? 0),
            'pageviews' => (int) ($pageviews[$hour] ?? 0),
            'created_at' => $now,
            'updated_at' => $now,
        ], range(0, 23)));
    }

    private function summarizePages(WebAnalyticsQuery $query, WebAnalyticsFilters $filters, string $day, Carbon $now): void
    {
        $rows = $query->pageviews($filters)
            ->toBase()
            ->selectRaw('web_pageviews.path as path, COUNT(*) as pageviews, COUNT(DISTINCT web_pageviews.web_visitor_id) as visitors, SUM(web_pageviews.is_entry) as entries, SUM(web_pageviews.is_exit) as exits, SUM(CASE WHEN web_pageviews.is_entry = 1 AND web_sessions.is_bounce = 1 THEN 1 ELSE 0 END) as bounces, SUM(web_pageviews.engaged_ms) as engaged_ms, SUM(web_pageviews.max_scroll_pct) as scroll, SUM(web_pageviews.is_not_found) as not_found')
            ->groupBy('web_pageviews.path')
            ->get()
            ->map(fn (object $row): array => [
                'date' => $day,
                'path' => $row->path,
                'pageviews' => (int) $row->pageviews,
                'visitors' => (int) $row->visitors,
                'entries' => (int) $row->entries,
                'exits' => (int) $row->exits,
                'bounces' => (int) $row->bounces,
                'total_engaged_ms' => (int) $row->engaged_ms,
                'total_scroll_pct' => (int) $row->scroll,
                'not_found' => (int) $row->not_found,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        WebDailyPageSummary::query()->where('date', $day)->delete();

        foreach ($rows->chunk(500) as $chunk) {
            WebDailyPageSummary::insert($chunk->values()->all());
        }
    }

    private function summarizeDimensions(WebAnalyticsQuery $query, WebAnalyticsFilters $filters, string $day, Carbon $now): void
    {
        WebDailyDimensionSummary::query()->where('date', $day)->delete();

        foreach (WebDimensions::sessionKeys() as $dimension) {
            $value = 'COALESCE('.WebDimensions::expression($dimension).", '".WebAnalyticsQuery::NONE."')";

            // Values longer than the column (a 512-char entry path) are cut,
            // which can merge two of them — rare enough to accept.
            $rows = $query->sessions($filters)
                ->toBase()
                ->selectRaw("{$value} as value, COUNT(DISTINCT web_visitor_id) as visitors, COUNT(*) as sessions, SUM(pageviews_count) as pageviews, SUM(is_bounce) as bounces, SUM(duration_seconds) as total_duration, SUM(has_conversion) as conversions")
                ->groupByRaw($value)
                ->get()
                ->groupBy(fn (object $row): string => mb_substr((string) $row->value, 0, 255))
                ->map(fn ($group, string $truncated): array => [
                    'date' => $day,
                    'dimension' => $dimension,
                    'value' => $truncated,
                    'visitors' => (int) $group->sum('visitors'),
                    'sessions' => (int) $group->sum('sessions'),
                    'pageviews' => (int) $group->sum('pageviews'),
                    'bounces' => (int) $group->sum('bounces'),
                    'total_duration_seconds' => (int) $group->sum('total_duration'),
                    'conversions' => (int) $group->sum('conversions'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

            foreach ($rows->values()->chunk(500) as $chunk) {
                WebDailyDimensionSummary::insert($chunk->values()->all());
            }
        }
    }
}
