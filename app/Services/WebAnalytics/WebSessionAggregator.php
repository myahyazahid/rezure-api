<?php

namespace App\Services\WebAnalytics;

use App\Models\WebEvent;
use App\Models\WebPageview;
use App\Models\WebSession;
use App\Models\WebVisitor;
use Illuminate\Support\Carbon;

/**
 * Recomputes a session's and a visitor's rolled-up fields (counts,
 * duration, bounce, entry/exit, ...) from the rows that actually exist,
 * rather than incrementing counters as hits arrive. A retried job, a batch
 * delivered twice, or hits arriving out of order then all converge on the
 * same numbers — the idempotency guarantee the ingestion contract makes.
 */
class WebSessionAggregator
{
    public function refreshSession(WebSession $session): void
    {
        $pageviews = WebPageview::query()
            ->where('web_session_id', $session->id)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['id', 'path', 'occurred_at', 'engaged_ms', 'sequence', 'is_entry', 'is_exit']);

        $this->renumberPageviews($pageviews->all());

        $events = WebEvent::query()->where('web_session_id', $session->id);
        $eventsCount = (clone $events)->count();
        $lastEventAt = (clone $events)->max('occurred_at');
        $hasConversion = (clone $events)->where('name', WebEvent::DOWNLOAD)->exists();

        $startedAt = $session->started_at;
        $endedAt = $startedAt->copy();

        if ($pageviews->isNotEmpty()) {
            $startedAt = $pageviews->first()->occurred_at->min($startedAt);
            $session->entry_path = $pageviews->first()->path;
            $session->exit_path = $pageviews->last()->path;

            // A page's engaged time extends the visit past its pageview
            // timestamp — otherwise every single-page visit lasts 0 s.
            foreach ($pageviews as $pageview) {
                $endedAt = $endedAt->max($pageview->occurred_at->copy()->addMilliseconds($pageview->engaged_ms));
            }
        }

        if ($lastEventAt !== null) {
            $endedAt = $endedAt->max(Carbon::parse($lastEventAt));
        }

        $engagedSeconds = intdiv((int) $pageviews->sum('engaged_ms'), 1000);
        $isEngaged = $engagedSeconds >= config('web-analytics.engaged_session_seconds')
            || $pageviews->count() >= 2
            || $hasConversion;

        $session->fill([
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => (int) $startedAt->diffInSeconds($endedAt),
            'engaged_seconds' => $engagedSeconds,
            'pageviews_count' => $pageviews->count(),
            'events_count' => $eventsCount,
            'has_conversion' => $hasConversion,
            'is_engaged' => $isEngaged,
            'is_bounce' => ! $isEngaged,
        ]);

        $session->save();
    }

    public function refreshVisitor(WebVisitor $visitor): void
    {
        $stats = WebSession::query()
            ->where('web_visitor_id', $visitor->id)
            ->selectRaw('COUNT(*) as sessions_count, COALESCE(SUM(pageviews_count), 0) as pageviews_count, MIN(started_at) as first_started_at, MAX(ended_at) as last_ended_at')
            ->first();

        $latest = WebSession::query()
            ->where('web_visitor_id', $visitor->id)
            ->latest('started_at')
            ->first(['ip_address', 'country_code', 'city', 'browser', 'os', 'device_type']);

        $firstDownloadAt = WebEvent::query()
            ->where('web_visitor_id', $visitor->id)
            ->where('name', WebEvent::DOWNLOAD)
            ->min('occurred_at');

        $visitor->sessions_count = (int) $stats->sessions_count;
        $visitor->pageviews_count = (int) $stats->pageviews_count;

        if ($stats->first_started_at !== null) {
            $visitor->first_seen_at = $visitor->first_seen_at->min(Carbon::parse($stats->first_started_at));
            $visitor->last_seen_at = $visitor->last_seen_at->max(Carbon::parse($stats->last_ended_at));
        }

        if ($latest !== null) {
            $visitor->fill([
                'last_ip_address' => $latest->ip_address,
                'last_country_code' => $latest->country_code,
                'last_city' => $latest->city,
                'last_browser' => $latest->browser,
                'last_os' => $latest->os,
                'last_device_type' => $latest->device_type,
            ]);
        }

        $visitor->first_download_at = $firstDownloadAt !== null ? Carbon::parse($firstDownloadAt) : null;
        $visitor->save();
    }

    /**
     * Sequence, entry and exit follow the pageviews' timestamps, so a
     * pageview arriving late (from a slower batch) slots into place.
     *
     * @param  list<WebPageview>  $pageviews
     */
    private function renumberPageviews(array $pageviews): void
    {
        $lastIndex = count($pageviews) - 1;

        foreach ($pageviews as $index => $pageview) {
            $expected = [
                'sequence' => $index + 1,
                'is_entry' => $index === 0,
                'is_exit' => $index === $lastIndex,
            ];

            if ($pageview->sequence !== $expected['sequence'] || $pageview->is_entry !== $expected['is_entry'] || $pageview->is_exit !== $expected['is_exit']) {
                WebPageview::query()->whereKey($pageview->id)->update($expected);
            }
        }
    }
}
