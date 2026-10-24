<?php

namespace App\Services\WebAnalytics\Reports;

use App\Models\WebPageview;
use App\Models\WebSession;
use App\Support\WebDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Website › Realtime: who is on the site right now. Read straight from the
 * raw tables (never cached) — "now" is measured by session ended_at, which
 * engagement hits keep pushing forward while a tab stays open.
 */
class RealtimeReport
{
    private const ACTIVE_MINUTES = 5;

    private const WINDOW_MINUTES = 30;

    /**
     * Plain arrays only — this is polled as JSON every 10 seconds.
     *
     * @return array{active_visitors: int, pageviews_30m: int, minutes: list<array{label: string, pageviews: int}>, pages: list<array{path: string, visitors: int}>, sources: list<array{label: string, visitors: int}>, countries: list<array{label: string, visitors: int}>, feed: list<array{path: string, where: string, source: string, ago: string}>}
     */
    public function snapshot(): array
    {
        $activeSince = now()->subMinutes(self::ACTIVE_MINUTES);
        $windowStart = now()->subMinutes(self::WINDOW_MINUTES)->startOfMinute();

        $active = WebSession::query()->where('ended_at', '>=', $activeSince);

        $recentPageviews = WebPageview::query()
            ->where('occurred_at', '>=', $windowStart)
            ->pluck('occurred_at');

        return [
            'active_visitors' => (clone $active)->distinct()->count('web_visitor_id'),
            'pageviews_30m' => $recentPageviews->count(),
            'minutes' => $this->perMinute($recentPageviews->all(), $windowStart),
            'pages' => WebPageview::query()
                ->where('occurred_at', '>=', $activeSince)
                ->selectRaw('path, COUNT(DISTINCT web_visitor_id) as visitors')
                ->groupBy('path')
                ->orderByDesc('visitors')
                ->limit(10)
                ->get()
                ->map(fn (WebPageview $row): array => ['path' => $row->path, 'visitors' => (int) $row->visitors])
                ->all(),
            'sources' => $this->activeBy((clone $active), 'source', fn (?string $value): string => WebDisplay::value('source', $value)),
            'countries' => $this->activeBy((clone $active), 'country_code', fn (?string $value): string => WebDisplay::value('country', $value)),
            'feed' => WebPageview::query()
                ->with('session:id,country_code,city,browser,device_type,source')
                ->latest('occurred_at')
                ->limit(15)
                ->get()
                ->map(fn (WebPageview $pageview): array => [
                    'path' => $pageview->path,
                    'where' => trim(WebDisplay::flag($pageview->session?->country_code).' '.($pageview->session?->city ?? '').' · '.($pageview->session?->browser ?? '?').' · '.($pageview->session?->device_type ?? '?')),
                    'source' => WebDisplay::value('source', $pageview->session?->source),
                    'ago' => $pageview->occurred_at->diffForHumans(short: true),
                ])
                ->all(),
        ];
    }

    /**
     * @param  Builder<WebSession>  $sessions
     * @param  callable(?string): string  $label
     * @return list<array{label: string, visitors: int}>
     */
    private function activeBy($sessions, string $column, callable $label): array
    {
        return $sessions
            ->toBase()
            ->selectRaw("{$column} as value, COUNT(DISTINCT web_visitor_id) as visitors")
            ->groupBy($column)
            ->orderByDesc('visitors')
            ->limit(8)
            ->get()
            ->map(fn (object $row): array => ['label' => $label($row->value), 'visitors' => (int) $row->visitors])
            ->all();
    }

    /**
     * @param  list<Carbon>  $timestamps
     * @return list<array{label: string, pageviews: int}>
     */
    private function perMinute(array $timestamps, Carbon $windowStart): array
    {
        $counts = array_fill(0, self::WINDOW_MINUTES + 1, 0);

        foreach ($timestamps as $timestamp) {
            $index = (int) $windowStart->diffInMinutes($timestamp);

            if ($index >= 0 && $index <= self::WINDOW_MINUTES) {
                $counts[$index]++;
            }
        }

        $minutes = [];

        foreach ($counts as $index => $count) {
            $minutes[] = [
                'label' => $windowStart->copy()->addMinutes($index)->setTimezone(config('app.display_timezone'))->format('H:i'),
                'pageviews' => $count,
            ];
        }

        return $minutes;
    }
}
