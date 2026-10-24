@php
    use App\Support\WebDisplay;

    $tz = config('app.display_timezone');
@endphp

<x-website.layout :title="'Visitor '.$visitor->short_id" :subtitle="'First seen '.$visitor->first_seen_at->setTimezone($tz)->format('d M Y H:i').' · last seen '.$visitor->last_seen_at->diffForHumans()">
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
        <x-website.kpi label="Visits" :value="number_format($visitor->sessions_count)" />
        <x-website.kpi label="Pageviews" :value="number_format($visitor->pageviews_count)" />
        <x-website.kpi label="First came from" :value="$visitor->first_source ?? 'Direct'" />
        <x-website.kpi label="First landing" :value="$visitor->first_landing_path ?? '–'" />
        <x-website.kpi label="Downloaded" :value="$visitor->first_download_at?->setTimezone($tz)->format('d M Y') ?? 'Not yet'" />
        <x-website.kpi label="Desktop install" :value="$visitor->device?->device_name ?? ($visitor->device ? $visitor->device->short_id : '–')" hint="Matched by download IP — probabilistic" />
    </div>

    <x-website.card title="Identity" class="mt-4">
        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs text-subtle">Visitor id</dt><dd class="font-mono text-xs">{{ $visitor->visitor_uuid }}</dd></div>
            <div><dt class="text-xs text-subtle">Last IP</dt><dd>{{ $visitor->last_ip_address ?? '–' }}</dd></div>
            <div><dt class="text-xs text-subtle">Location</dt><dd>{{ WebDisplay::flag($visitor->last_country_code) }} {{ $visitor->last_city ?? 'Unknown' }}</dd></div>
            <div><dt class="text-xs text-subtle">Device</dt><dd>{{ $visitor->last_browser ?? '?' }} · {{ $visitor->last_os ?? '?' }} · {{ $visitor->last_device_type ?? '?' }}</dd></div>
            <div><dt class="text-xs text-subtle">First channel</dt><dd>{{ $visitor->first_channel ?? '–' }}</dd></div>
            <div><dt class="text-xs text-subtle">First campaign</dt><dd>{{ collect([$visitor->first_utm_source, $visitor->first_utm_medium, $visitor->first_utm_campaign])->filter()->implode(' / ') ?: '–' }}</dd></div>
            @if ($visitor->device)
                <div><dt class="text-xs text-subtle">Linked install</dt><dd><a href="{{ route('dashboard.devices.show', $visitor->device) }}" class="text-brand hover:underline">{{ $visitor->device->device_name ?? $visitor->device->short_id }}</a></dd></div>
            @endif
        </dl>
    </x-website.card>

    <div class="mt-4 space-y-4">
        @foreach ($visitor->sessions as $session)
            <x-website.card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <a href="{{ route('dashboard.website.sessions.show', $session) }}" class="text-sm font-medium hover:text-brand">
                            Visit #{{ $session->visit_number }} · {{ $session->started_at->setTimezone($tz)->format('d M Y H:i') }}
                        </a>
                        <p class="mt-0.5 text-xs text-muted">
                            {{ $session->channel ?? 'Direct' }} · {{ $session->source ?? 'Direct' }}
                            · {{ WebDisplay::flag($session->country_code) }} {{ $session->city ?? 'Unknown' }}
                            · {{ $session->browser }} {{ $session->browser_version }} · {{ $session->os }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-xs">
                        <span class="rounded-full bg-surface-raised px-2 py-0.5 text-muted">{{ WebDisplay::duration($session->duration_seconds) }}</span>
                        @if ($session->has_conversion)
                            <span class="rounded-full bg-positive/15 px-2 py-0.5 text-positive">downloaded</span>
                        @elseif ($session->is_bounce)
                            <span class="rounded-full bg-surface-raised px-2 py-0.5 text-subtle">bounced</span>
                        @endif
                    </div>
                </div>

                <ol class="mt-3 space-y-1 border-l border-border pl-4 text-sm">
                    @php
                        $timeline = $session->pageviews->map(fn ($pageview) => ['at' => $pageview->occurred_at, 'kind' => 'page', 'item' => $pageview])
                            ->concat($session->events->map(fn ($event) => ['at' => $event->occurred_at, 'kind' => 'event', 'item' => $event]))
                            ->sortBy('at');
                    @endphp
                    @foreach ($timeline as $entry)
                        <li class="flex items-center justify-between gap-3">
                            @if ($entry['kind'] === 'page')
                                <span class="truncate">{{ $entry['item']->path }}</span>
                                <span class="shrink-0 text-xs text-subtle">{{ WebDisplay::duration($entry['item']->engaged_ms / 1000) }} · {{ $entry['item']->max_scroll_pct }}% · {{ $entry['at']->setTimezone($tz)->format('H:i:s') }}</span>
                            @else
                                <span class="truncate text-brand">⚡ {{ $entry['item']->name }}@if ($entry['item']->label): <span class="text-muted">{{ $entry['item']->label }}</span>@endif</span>
                                <span class="shrink-0 text-xs text-subtle">{{ $entry['at']->setTimezone($tz)->format('H:i:s') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-website.card>
        @endforeach
    </div>
</x-website.layout>
