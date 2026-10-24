<?php

namespace App\Services\WebAnalytics;

use App\Models\WebBotHitSummary;
use App\Models\WebClick;
use App\Models\WebError;
use App\Models\WebEvent;
use App\Models\WebPageview;
use App\Models\WebSession;
use App\Models\WebVisitor;
use App\Services\GeolocationResolver;
use App\Support\ViewportBucket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stores one validated batch from the website tracker (POST /site/hit):
 * visitor → session → pageviews, then everything that hangs off a
 * pageview. Runs inside ProcessSiteHitBatchJob, never in the request.
 *
 * Every row is keyed by the client's UUID and written with createOrFirst,
 * and aggregates are recomputed from rows (WebSessionAggregator), so the
 * same batch processed twice changes nothing the second time.
 */
class SiteHitProcessor
{
    /**
     * @var list<string>
     */
    private const UTM_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /**
     * @var list<string>
     */
    private const VITAL_FIELDS = ['lcp_ms', 'inp_ms', 'cls', 'fcp_ms', 'ttfb_ms', 'dom_load_ms', 'page_load_ms'];

    public function __construct(
        private readonly UserAgentParser $userAgents,
        private readonly GeolocationResolver $geolocation,
        private readonly TrafficSourceClassifier $sources,
        private readonly WebSessionAggregator $aggregator,
    ) {}

    /**
     * @param  array<string, mixed>  $batch  the validated SiteHitRequest payload
     * @param  array{ip: ?string, user_agent: ?string, accept_language: ?string, origin: ?string, received_at: string}  $request
     * @return list<array<string, mixed>> hits whose pageview isn't stored yet — the caller retries them later
     */
    public function process(array $batch, array $request): array
    {
        if (! $this->isAllowedOrigin($request['origin'] ?? null) || ! $this->pageviewsAreOnAllowedHosts($batch['hits'])) {
            return [];
        }

        $agent = $this->userAgents->parse($request['user_agent'] ?? null);
        $receivedAt = Carbon::parse($request['received_at']);

        if ($agent['is_bot']) {
            $this->countBotHits($agent, $receivedAt, count($batch['hits']));

            return [];
        }

        $hits = $this->timestampHits($batch['hits'], Carbon::parse($batch['sent_at']), $receivedAt);

        if ($hits === []) {
            return [];
        }

        return DB::transaction(function () use ($batch, $request, $agent, $hits): array {
            $pageviewHits = array_values(array_filter($hits, fn (array $hit): bool => $hit['type'] === 'pageview'));
            $session = WebSession::query()->where('session_uuid', $batch['session_id'])->first();

            if ($session === null && $pageviewHits !== []) {
                $session = $this->openSession($batch, $request, $agent, $pageviewHits[0]);
            }

            if ($session === null) {
                return array_map($this->withoutTimestamp(...), $hits);
            }

            /** @var array<int, WebSession> $touchedSessions */
            $touchedSessions = [$session->id => $session];

            foreach ($pageviewHits as $hit) {
                $this->storePageview($session, $hit);
            }

            $deferred = [];

            foreach ($hits as $hit) {
                if ($hit['type'] === 'pageview') {
                    continue;
                }

                $pageview = WebPageview::query()->where('pageview_uuid', $hit['pageview_id'])->first();

                if ($pageview === null) {
                    $deferred[] = $this->withoutTimestamp($hit);

                    continue;
                }

                $owner = $touchedSessions[$pageview->web_session_id] ??= $pageview->session;

                match ($hit['type']) {
                    'engagement' => $this->storeEngagement($pageview, $hit),
                    'vitals' => $this->storeVitals($pageview, $hit),
                    'event' => $this->storeEvent($owner, $pageview, $hit),
                    'click' => $this->storeClick($owner, $pageview, $hit),
                    'error' => $this->storeError($owner, $pageview, $hit),
                    default => null,
                };
            }

            foreach ($touchedSessions as $touched) {
                $this->aggregator->refreshSession($touched);
                $this->aggregator->refreshVisitor($touched->visitor);
            }

            return $deferred;
        });
    }

    /**
     * @param  array<string, mixed>  $batch
     * @param  array{ip: ?string, user_agent: ?string, accept_language: ?string, origin: ?string, received_at: string}  $request
     * @param  array<string, mixed>  $agent
     * @param  array<string, mixed>  $landing
     */
    private function openSession(array $batch, array $request, array $agent, array $landing): WebSession
    {
        $acquisition = $this->acquisition($landing);

        $visitor = WebVisitor::createOrFirst(
            ['visitor_uuid' => $batch['visitor_id']],
            [
                'first_seen_at' => $landing['occurred_at'],
                'last_seen_at' => $landing['occurred_at'],
                ...$this->firstTouch($acquisition),
            ],
        );

        $location = $this->geolocation->resolveLocation($request['ip'] ?? null);

        return WebSession::createOrFirst(
            ['session_uuid' => $batch['session_id']],
            [
                'web_visitor_id' => $visitor->id,
                'started_at' => $landing['occurred_at'],
                'ended_at' => $landing['occurred_at'],
                ...$acquisition,
                'ip_address' => $request['ip'] ?? null,
                'country_code' => $location['country_code'] ?? null,
                'region' => $this->limit($location['region'] ?? null, 100),
                'city' => $this->limit($location['city'] ?? null, 100),
                'postal_code' => $this->limit($location['postal_code'] ?? null, 20),
                'latitude' => $location['latitude'] ?? null,
                'longitude' => $location['longitude'] ?? null,
                'geo_timezone' => $this->limit($location['timezone'] ?? null, 64),
                'browser' => $agent['browser'],
                'browser_version' => $this->limit($agent['browser_version'], 32),
                'browser_engine' => $this->limit($agent['browser_engine'], 32),
                'os' => $agent['os'],
                'os_version' => $this->limit($agent['os_version'], 32),
                'device_type' => $this->limit($agent['device_type'], 32),
                'device_brand' => $agent['device_brand'],
                'device_model' => $agent['device_model'],
                'user_agent' => $request['user_agent'] ?? null,
                'accept_language' => $this->limit($request['accept_language'] ?? null, 255),
                ...$this->contextAttributes($batch['context']),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function storePageview(WebSession $session, array $hit): void
    {
        $url = $this->parseUrl($hit['url']);

        WebPageview::createOrFirst(
            ['pageview_uuid' => $hit['pageview_id']],
            [
                'web_session_id' => $session->id,
                'web_visitor_id' => $session->web_visitor_id,
                'url' => $hit['url'],
                'path' => $url['path'],
                'query_string' => $url['query'],
                'hash' => $url['hash'],
                'title' => $this->limit($hit['title'] ?? null, 255),
                'referrer_url' => $hit['referrer'] ?? null,
                'occurred_at' => $hit['occurred_at'],
                'is_not_found' => (bool) ($hit['not_found'] ?? false),
            ],
        );

        // The landing pageview's batch can be processed after a later one
        // that already opened the session — whichever pageview is earliest
        // decides where the visit came from.
        if ($hit['occurred_at']->lt($session->started_at)) {
            $acquisition = $this->acquisition($hit);
            $session->fill([...$acquisition, 'started_at' => $hit['occurred_at']])->save();

            $visitor = $session->visitor;

            if ($hit['occurred_at']->lt($visitor->first_seen_at)) {
                $visitor->fill([...$this->firstTouch($acquisition), 'first_seen_at' => $hit['occurred_at']])->save();
            }
        }
    }

    /**
     * Engagement may be reported several times for one pageview (route
     * change, then tab hidden, ...) — keep the largest values seen.
     *
     * @param  array<string, mixed>  $hit
     */
    private function storeEngagement(WebPageview $pageview, array $hit): void
    {
        $milestones = array_values(array_unique([
            ...($pageview->scroll_milestones ?? []),
            ...array_map('intval', $hit['scroll_milestones'] ?? []),
        ]));
        sort($milestones);

        $pageview->fill([
            'engaged_ms' => max($pageview->engaged_ms, (int) $hit['engaged_ms']),
            'max_scroll_pct' => max($pageview->max_scroll_pct, min(100, (int) $hit['max_scroll_pct'])),
            'scroll_milestones' => $milestones,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function storeVitals(WebPageview $pageview, array $hit): void
    {
        foreach (self::VITAL_FIELDS as $field) {
            if (isset($hit[$field])) {
                $pageview->{$field} = $hit[$field];
            }
        }

        $pageview->save();
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function storeEvent(WebSession $session, WebPageview $pageview, array $hit): void
    {
        // Downloads are only ever recorded server-side by GET /site/download;
        // a client claiming one would double-count the main conversion.
        if ($hit['name'] === WebEvent::DOWNLOAD) {
            return;
        }

        WebEvent::createOrFirst(
            ['event_uuid' => $hit['id']],
            [
                'web_session_id' => $session->id,
                'web_visitor_id' => $session->web_visitor_id,
                'web_pageview_id' => $pageview->id,
                'name' => $hit['name'],
                'label' => $this->limit($hit['label'] ?? null, 255),
                'props' => $this->scalarProps($hit['props'] ?? null),
                'path' => $pageview->path,
                'occurred_at' => $hit['occurred_at'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function storeClick(WebSession $session, WebPageview $pageview, array $hit): void
    {
        WebClick::createOrFirst(
            ['click_uuid' => $hit['id']],
            [
                'web_pageview_id' => $pageview->id,
                'web_session_id' => $session->id,
                'path' => $pageview->path,
                'x_pct' => round(min(100, max(0, (float) $hit['x_pct'])), 2),
                'y_px' => (int) $hit['y_px'],
                'page_height' => (int) $hit['page_height'],
                'viewport_width' => (int) $hit['viewport_width'],
                'viewport_bucket' => ViewportBucket::for((int) $hit['viewport_width']) ?? 'desktop',
                'selector' => $this->limit($hit['selector'] ?? null, 255),
                'tag' => $this->limit($hit['tag'] ?? null, 32),
                'text' => $this->limit($hit['text'] ?? null, 120),
                'href' => $hit['href'] ?? null,
                'is_rage' => (bool) ($hit['is_rage'] ?? false),
                'occurred_at' => $hit['occurred_at'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $hit
     */
    private function storeError(WebSession $session, WebPageview $pageview, array $hit): void
    {
        $line = isset($hit['line']) ? (int) $hit['line'] : null;

        WebError::createOrFirst(
            ['error_uuid' => $hit['id']],
            [
                'web_session_id' => $session->id,
                'web_pageview_id' => $pageview->id,
                'fingerprint' => WebError::fingerprintFor($hit['message'], $hit['source'] ?? null, $line),
                'message' => $hit['message'],
                'source' => $hit['source'] ?? null,
                'line' => $line,
                'column' => isset($hit['column']) ? (int) $hit['column'] : null,
                'stack' => $hit['stack'] ?? null,
                'path' => $pageview->path,
                'browser' => $session->browser,
                'browser_version' => $session->browser_version,
                'os' => $session->os,
                'occurred_at' => $hit['occurred_at'],
            ],
        );
    }

    /**
     * Where a session came from, decided by its landing pageview.
     *
     * @param  array<string, mixed>  $landing
     * @return array<string, ?string>
     */
    private function acquisition(array $landing): array
    {
        $url = $this->parseUrl($landing['url']);
        $utm = [];

        foreach (self::UTM_PARAMS as $param) {
            $utm[$param] = $this->limit(is_string($url['params'][$param] ?? null) ? trim($url['params'][$param]) : null, 150);
        }

        $clickIdType = null;
        $clickId = null;

        foreach (config('web-analytics.click_id_params') as $param => $network) {
            if (is_string($url['params'][$param] ?? null) && $url['params'][$param] !== '') {
                $clickIdType = $network;
                $clickId = $this->limit($url['params'][$param], 255);

                break;
            }
        }

        $classified = $this->sources->classify($landing['referrer'] ?? null, $utm, $clickIdType);

        return [
            'entry_path' => $url['path'],
            'referrer_url' => $classified['referrer_host'] !== null ? ($landing['referrer'] ?? null) : null,
            'referrer_host' => $this->limit($classified['referrer_host'], 255),
            'channel' => $classified['channel'],
            'source' => $classified['source'],
            ...$utm,
            'click_id_type' => $clickIdType,
            'click_id' => $clickId,
        ];
    }

    /**
     * @param  array<string, ?string>  $acquisition
     * @return array<string, ?string>
     */
    private function firstTouch(array $acquisition): array
    {
        return [
            'first_landing_path' => $acquisition['entry_path'],
            'first_referrer_host' => $acquisition['referrer_host'],
            'first_channel' => $acquisition['channel'],
            'first_source' => $acquisition['source'],
            'first_utm_source' => $acquisition['utm_source'],
            'first_utm_medium' => $acquisition['utm_medium'],
            'first_utm_campaign' => $acquisition['utm_campaign'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function contextAttributes(array $context): array
    {
        $viewportWidth = isset($context['viewport_width']) ? (int) $context['viewport_width'] : null;

        return [
            'is_new_visitor' => (bool) ($context['is_new_visitor'] ?? false),
            'visit_number' => max(1, (int) ($context['visit_number'] ?? 1)),
            'screen_width' => $context['screen_width'] ?? null,
            'screen_height' => $context['screen_height'] ?? null,
            'viewport_width' => $viewportWidth,
            'viewport_height' => $context['viewport_height'] ?? null,
            'viewport_bucket' => ViewportBucket::for($viewportWidth),
            'pixel_ratio' => isset($context['pixel_ratio']) ? round((float) $context['pixel_ratio'], 2) : null,
            'language' => $this->limit($context['language'] ?? null, 35),
            'languages' => isset($context['languages']) ? array_slice(array_values($context['languages']), 0, 10) : null,
            'timezone' => $this->limit($context['timezone'] ?? null, 64),
            'color_scheme' => $context['color_scheme'] ?? null,
            'site_theme' => $context['site_theme'] ?? null,
            'connection' => $this->limit($context['connection'] ?? null, 16),
            'cpu_cores' => $context['cpu_cores'] ?? null,
            'memory_gb' => $context['memory_gb'] ?? null,
            'touch_points' => $context['touch_points'] ?? null,
            'do_not_track' => $context['do_not_track'] ?? null,
        ];
    }

    /**
     * Shifts each hit's client time by the client's clock skew (measured
     * from `sent_at` vs when the server received the batch) and drops hits
     * too old to belong in the analytics anymore.
     *
     * @param  list<array<string, mixed>>  $hits
     * @return list<array<string, mixed>>
     */
    private function timestampHits(array $hits, Carbon $sentAt, Carbon $receivedAt): array
    {
        $skewMs = (int) $sentAt->diffInMilliseconds($receivedAt, false);
        $oldest = $receivedAt->copy()->subDays(config('web-analytics.max_hit_age_days'));
        $timestamped = [];

        foreach ($hits as $hit) {
            $occurredAt = Carbon::parse($hit['at'])->utc()->addMilliseconds($skewMs);

            if ($occurredAt->gt($receivedAt)) {
                $occurredAt = $receivedAt->copy();
            }

            if ($occurredAt->lt($oldest)) {
                continue;
            }

            $timestamped[] = [...$hit, 'occurred_at' => $occurredAt];
        }

        usort($timestamped, fn (array $a, array $b): int => $a['occurred_at'] <=> $b['occurred_at']);

        return $timestamped;
    }

    /**
     * @param  array<string, mixed>  $hit
     * @return array<string, mixed>
     */
    private function withoutTimestamp(array $hit): array
    {
        unset($hit['occurred_at']);

        return $hit;
    }

    /**
     * @param  array<string, mixed>  $agent
     */
    private function countBotHits(array $agent, Carbon $receivedAt, int $hitCount): void
    {
        $summary = WebBotHitSummary::createOrFirst(
            [
                'date' => $receivedAt->copy()->setTimezone(config('app.display_timezone'))->toDateString(),
                'bot_name' => $this->limit($agent['bot_name'], 100),
            ],
            ['bot_category' => $this->limit($agent['bot_category'], 64)],
        );

        $summary->increment('hits', $hitCount);
    }

    private function isAllowedOrigin(?string $origin): bool
    {
        if ($origin === null || $origin === '' || $origin === 'null') {
            return true;
        }

        return $this->isAllowedHost(parse_url($origin, PHP_URL_HOST));
    }

    /**
     * @param  list<array<string, mixed>>  $hits
     */
    private function pageviewsAreOnAllowedHosts(array $hits): bool
    {
        foreach ($hits as $hit) {
            if ($hit['type'] === 'pageview' && ! $this->isAllowedHost(parse_url($hit['url'], PHP_URL_HOST))) {
                return false;
            }
        }

        return true;
    }

    private function isAllowedHost(mixed $host): bool
    {
        $allowed = config('web-analytics.allowed_hosts');

        if ($allowed === []) {
            return true;
        }

        if (! is_string($host)) {
            return false;
        }

        $host = preg_replace('/^www\./', '', strtolower($host));

        return in_array($host, array_map(fn (string $allowedHost): string => preg_replace('/^www\./', '', strtolower($allowedHost)), $allowed), true);
    }

    /**
     * `/guide/` and `/guide/index.html` are the same VitePress page as
     * `/guide` (cleanUrls), so paths are normalized before they're grouped.
     *
     * @return array{path: string, query: ?string, hash: ?string, params: array<string, mixed>}
     */
    public function parseUrl(string $url): array
    {
        $parts = parse_url($url) ?: [];
        $path = rawurldecode($parts['path'] ?? '/');
        $path = preg_replace('#(/index)?\.html$#', '', $path);
        $path = '/'.trim($path, '/');

        parse_str($parts['query'] ?? '', $params);

        return [
            'path' => mb_substr($path, 0, 512),
            'query' => $this->limit($parts['query'] ?? null, 1024),
            'hash' => $this->limit($parts['fragment'] ?? null, 255),
            'params' => $params,
        ];
    }

    /**
     * @return array<string, scalar|null>|null
     */
    private function scalarProps(mixed $props): ?array
    {
        if (! is_array($props) || $props === []) {
            return null;
        }

        $clean = [];

        foreach (array_slice($props, 0, 20, true) as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $clean[mb_substr((string) $key, 0, 64)] = is_string($value) ? mb_substr($value, 0, 255) : $value;
            }
        }

        return $clean === [] ? null : $clean;
    }

    private function limit(?string $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $length);
    }
}
