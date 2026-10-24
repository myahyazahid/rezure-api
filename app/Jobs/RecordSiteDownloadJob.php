<?php

namespace App\Jobs;

use App\Models\WebBotHitSummary;
use App\Models\WebEvent;
use App\Models\WebPageview;
use App\Models\WebSession;
use App\Models\WebVisitor;
use App\Services\WebAnalytics\UserAgentParser;
use App\Services\WebAnalytics\WebSessionAggregator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

class RecordSiteDownloadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array{event_uuid: string, visitor_uuid: ?string, session_uuid: ?string, pageview_uuid: ?string, version: ?string, ip: ?string, user_agent: ?string, referer: ?string, occurred_at: string}  $data
     */
    public function __construct(private readonly array $data)
    {
        $this->onQueue(config('web-analytics.queue'));
    }

    public function handle(UserAgentParser $userAgents, WebSessionAggregator $aggregator): void
    {
        $agent = $userAgents->parse($this->data['user_agent']);
        $occurredAt = Carbon::parse($this->data['occurred_at']);

        if ($agent['is_bot']) {
            WebBotHitSummary::createOrFirst(
                ['date' => $occurredAt->copy()->setTimezone(config('app.display_timezone'))->toDateString(), 'bot_name' => mb_substr($agent['bot_name'], 0, 100)],
                ['bot_category' => $agent['bot_category']],
            )->increment('hits');

            return;
        }

        $session = $this->data['session_uuid'] !== null
            ? WebSession::query()->where('session_uuid', $this->data['session_uuid'])->first()
            : null;

        // The click that led here may still be sitting in a hit batch on the
        // queue — give the session a moment to exist before recording the
        // download without it.
        if ($this->data['session_uuid'] !== null && $session === null && $this->attempts() < $this->tries) {
            $this->release(30);

            return;
        }

        $visitor = $session?->visitor ?? ($this->data['visitor_uuid'] !== null
            ? WebVisitor::query()->where('visitor_uuid', $this->data['visitor_uuid'])->first()
            : null);

        $pageview = $this->data['pageview_uuid'] !== null
            ? WebPageview::query()->where('pageview_uuid', $this->data['pageview_uuid'])->first()
            : null;

        WebEvent::createOrFirst(
            ['event_uuid' => $this->data['event_uuid']],
            [
                'web_session_id' => $session?->id,
                'web_visitor_id' => $visitor?->id,
                'web_pageview_id' => $pageview?->id,
                'name' => WebEvent::DOWNLOAD,
                'label' => $this->data['version'],
                'props' => array_filter([
                    'referer' => $this->data['referer'] !== null ? mb_substr($this->data['referer'], 0, 255) : null,
                    'browser' => $agent['browser'],
                    'os' => $agent['os'],
                ]) ?: null,
                'path' => $pageview?->path,
                'ip_address' => $this->data['ip'],
                'occurred_at' => $occurredAt,
            ],
        );

        if ($session !== null) {
            $aggregator->refreshSession($session);
        }

        if ($visitor !== null) {
            $aggregator->refreshVisitor($visitor);
        }
    }
}
