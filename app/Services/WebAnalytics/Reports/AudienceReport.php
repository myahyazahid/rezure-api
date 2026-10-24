<?php

namespace App\Services\WebAnalytics\Reports;

use App\Models\WebSession;
use App\Services\WebAnalytics\LocalTime;
use App\Services\WebAnalytics\WebAnalyticsFilters;
use App\Services\WebAnalytics\WebAnalyticsQuery;
use App\Support\WebDisplay;
use Illuminate\Support\Carbon;

/**
 * Website › Audience: who the visitors are, where they are, and whether
 * they come back.
 */
class AudienceReport
{
    private const COHORT_WEEKS = 8;

    public function __construct(private readonly WebAnalyticsQuery $query) {}

    /**
     * @return array<string, mixed>
     */
    public function build(WebAnalyticsFilters $filters): array
    {
        return $this->query->remember($filters, 'audience', function () use ($filters): array {
            $countries = $this->query->breakdown($filters, 'country', 50);

            return [
                'countries' => $countries,
                'map' => $this->mapValues($countries),
                'regions' => $this->query->breakdown($filters, 'region', 15),
                'cities' => $this->query->breakdown($filters, 'city', 15),
                'cityPoints' => $this->cityPoints($filters),
                'newVsReturning' => $this->newVsReturning($filters),
                'visitorTypes' => $this->query->breakdown($filters, 'visitor_type', 2),
                'frequency' => $this->frequency($filters),
                'recency' => $this->recency($filters),
                'cohorts' => $this->cohorts(),
                'languages' => $this->query->breakdown($filters, 'language', 12),
                'timezones' => $this->query->breakdown($filters, 'timezone', 12),
            ];
        });
    }

    /**
     * Visitors per country keyed by ISO numeric code, as the map wants it.
     *
     * @param  list<array<string, mixed>>  $countries
     * @return array<string, int>
     */
    private function mapValues(array $countries): array
    {
        $values = [];

        foreach ($countries as $row) {
            $numeric = WebDisplay::numericCountryCode($row['value']);

            if ($numeric !== null) {
                $values[$numeric] = $row['visitors'];
            }
        }

        return $values;
    }

    /**
     * @return list<array{label: string, lat: float, lon: float, value: int}>
     */
    private function cityPoints(WebAnalyticsFilters $filters): array
    {
        return $this->query->sessions($filters)
            ->toBase()
            ->whereNotNull('city')
            ->whereNotNull('latitude')
            ->selectRaw('city, country_code, AVG(latitude) as lat, AVG(longitude) as lon, COUNT(DISTINCT web_visitor_id) as visitors')
            ->groupBy('city', 'country_code')
            ->orderByDesc('visitors')
            ->limit(150)
            ->get()
            ->map(fn (object $row): array => [
                'label' => $row->city.', '.$row->country_code,
                'lat' => round((float) $row->lat, 4),
                'lon' => round((float) $row->lon, 4),
                'value' => (int) $row->visitors,
            ])
            ->all();
    }

    /**
     * @return array{labels: list<string>, new: list<int>, returning: list<int>}
     */
    private function newVsReturning(WebAnalyticsFilters $filters): array
    {
        $granularity = $filters->granularity();
        $labels = $this->query->bucketLabels($filters, $granularity);
        $bucket = LocalTime::bucket('web_sessions.started_at', $granularity);

        $rows = $this->query->sessions($filters)
            ->toBase()
            ->selectRaw("{$bucket} as bucket, COUNT(DISTINCT CASE WHEN is_new_visitor = 1 THEN web_visitor_id END) as new_visitors, COUNT(DISTINCT CASE WHEN is_new_visitor = 0 THEN web_visitor_id END) as returning_visitors")
            ->groupByRaw($bucket)
            ->get()
            ->keyBy('bucket');

        return [
            'labels' => array_values($labels),
            'new' => array_map(fn (string $key): int => (int) ($rows[$key]->new_visitors ?? 0), array_keys($labels)),
            'returning' => array_map(fn (string $key): int => (int) ($rows[$key]->returning_visitors ?? 0), array_keys($labels)),
        ];
    }

    /**
     * Sessions by how many visits the visitor had made at that point.
     *
     * @return list<array{label: string, count: int}>
     */
    private function frequency(WebAnalyticsFilters $filters): array
    {
        $buckets = ['1st visit' => 0, '2nd' => 0, '3rd' => 0, '4–5' => 0, '6–10' => 0, '11+' => 0];

        $this->query->sessions($filters)
            ->toBase()
            ->selectRaw('visit_number, COUNT(*) as total')
            ->groupBy('visit_number')
            ->get()
            ->each(function (object $row) use (&$buckets): void {
                $key = match (true) {
                    $row->visit_number <= 1 => '1st visit',
                    $row->visit_number == 2 => '2nd',
                    $row->visit_number == 3 => '3rd',
                    $row->visit_number <= 5 => '4–5',
                    $row->visit_number <= 10 => '6–10',
                    default => '11+',
                };
                $buckets[$key] += (int) $row->total;
            });

        return $this->asBuckets($buckets);
    }

    /**
     * For returning visits: how long since that visitor's previous visit.
     *
     * @return list<array{label: string, count: int}>
     */
    private function recency(WebAnalyticsFilters $filters): array
    {
        $buckets = ['Same day' => 0, '1 day' => 0, '2–7 days' => 0, '8–30 days' => 0, '31+ days' => 0];

        $this->query->sessions($filters)
            ->select(['web_sessions.started_at'])
            ->selectSub(
                WebSession::query()
                    ->from('web_sessions as previous')
                    ->selectRaw('MAX(previous.started_at)')
                    ->whereColumn('previous.web_visitor_id', 'web_sessions.web_visitor_id')
                    ->whereColumn('previous.started_at', '<', 'web_sessions.started_at'),
                'previous_started_at',
            )
            ->limit(20000)
            ->get()
            ->each(function (WebSession $session) use (&$buckets): void {
                if ($session->previous_started_at === null) {
                    return;
                }

                $days = (int) Carbon::parse($session->previous_started_at)->diffInDays($session->started_at);
                $key = match (true) {
                    $days < 1 => 'Same day',
                    $days === 1 => '1 day',
                    $days <= 7 => '2–7 days',
                    $days <= 30 => '8–30 days',
                    default => '31+ days',
                };
                $buckets[$key]++;
            });

        return $this->asBuckets($buckets);
    }

    /**
     * Weekly retention: of the visitors first seen in week W, the share who
     * came back in week W+1, W+2, ... Always the last eight weeks — a
     * cohort table is about the trend, not the selected period.
     *
     * @return list<array{week: string, visitors: int, weeks: list<?float>}>
     */
    public function cohorts(): array
    {
        $timezone = config('app.display_timezone');
        $start = now($timezone)->startOfWeek(Carbon::MONDAY)->subWeeks(self::COHORT_WEEKS - 1);
        $cohortWeek = LocalTime::bucket('web_visitors.first_seen_at', 'week');
        $activeWeek = LocalTime::bucket('web_sessions.started_at', 'week');

        $rows = WebSession::query()
            ->join('web_visitors', 'web_visitors.id', '=', 'web_sessions.web_visitor_id')
            ->where('web_visitors.first_seen_at', '>=', $start->copy()->utc())
            ->toBase()
            ->selectRaw("{$cohortWeek} as cohort, {$activeWeek} as active_week, COUNT(DISTINCT web_sessions.web_visitor_id) as visitors")
            ->groupByRaw("{$cohortWeek}, {$activeWeek}")
            ->get()
            ->groupBy('cohort');

        $cohorts = [];

        for ($index = 0; $index < self::COHORT_WEEKS; $index++) {
            $week = $start->copy()->addWeeks($index);
            $weekRows = ($rows[$week->format('Y-m-d')] ?? collect())->keyBy('active_week');
            $size = (int) ($weekRows[$week->format('Y-m-d')]->visitors ?? 0);
            $retention = [];

            for ($offset = 1; $offset < self::COHORT_WEEKS - $index; $offset++) {
                $returned = (int) ($weekRows[$week->copy()->addWeeks($offset)->format('Y-m-d')]->visitors ?? 0);
                $retention[] = $size > 0 ? round($returned / $size * 100, 1) : null;
            }

            $cohorts[] = ['week' => $week->format('d M'), 'visitors' => $size, 'weeks' => $retention];
        }

        return $cohorts;
    }

    /**
     * @param  array<string, int>  $buckets
     * @return list<array{label: string, count: int}>
     */
    private function asBuckets(array $buckets): array
    {
        return array_map(fn (string $label, int $count): array => ['label' => $label, 'count' => $count], array_keys($buckets), $buckets);
    }
}
