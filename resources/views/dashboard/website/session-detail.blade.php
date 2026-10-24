@php
    use App\Support\WebDisplay;

    $tz = config('app.display_timezone');
@endphp

<x-website.layout :title="'Visit · '.$session->started_at->setTimezone($tz)->format('d M Y H:i')" :subtitle="'Visit #'.$session->visit_number.' of visitor '.$session->visitor->short_id">
    <x-slot:actions>
        <a href="{{ route('dashboard.website.visitors.show', $session->visitor) }}" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-muted hover:text-foreground">All visits of this visitor</a>
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
        <x-website.kpi label="Duration" :value="WebDisplay::duration($session->duration_seconds)" />
        <x-website.kpi label="Engaged" :value="WebDisplay::duration($session->engaged_seconds)" />
        <x-website.kpi label="Pages" :value="number_format($session->pageviews_count)" />
        <x-website.kpi label="Events" :value="number_format($session->events_count)" />
        <x-website.kpi label="Clicks" :value="number_format($session->clicks_count)" :hint="$session->rage_clicks_count.' rage clicks'" />
        <x-website.kpi label="Outcome" :value="$session->has_conversion ? 'Downloaded' : ($session->is_bounce ? 'Bounced' : 'Engaged')" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <x-website.card title="Pages in this visit" class="lg:col-span-2">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                            <th class="pb-2 font-medium">#</th>
                            <th class="pb-2 font-medium">Page</th>
                            <th class="pb-2 text-right font-medium">Engaged</th>
                            <th class="pb-2 text-right font-medium">Scroll</th>
                            <th class="pb-2 text-right font-medium">LCP</th>
                            <th class="pb-2 text-right font-medium">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($session->pageviews as $pageview)
                            <tr class="border-t border-border">
                                <td class="py-2 pr-2 text-subtle">{{ $pageview->sequence }}</td>
                                <td class="max-w-0 py-2 pr-3">
                                    <span class="block truncate" title="{{ $pageview->url }}">{{ $pageview->path }}</span>
                                    @if ($pageview->title)<span class="block truncate text-xs text-subtle">{{ $pageview->title }}</span>@endif
                                </td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::duration($pageview->engaged_ms / 1000) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ $pageview->max_scroll_pct }}%</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ $pageview->lcp_ms !== null ? number_format($pageview->lcp_ms).' ms' : '–' }}</td>
                                <td class="py-2 text-right text-xs text-muted">{{ $pageview->occurred_at->setTimezone($tz)->format('H:i:s') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-website.card>

        <x-website.card title="Context">
            <dl class="space-y-2 text-sm">
                @foreach ([
                    'Channel' => $session->channel,
                    'Source' => $session->source,
                    'Referrer' => $session->referrer_url,
                    'Campaign' => collect([$session->utm_source, $session->utm_medium, $session->utm_campaign])->filter()->implode(' / ') ?: null,
                    'IP' => $session->ip_address,
                    'Location' => trim(WebDisplay::flag($session->country_code).' '.collect([$session->city, $session->region, $session->postal_code])->filter()->implode(', ')),
                    'Browser' => trim($session->browser.' '.$session->browser_version.' ('.$session->browser_engine.')'),
                    'OS' => trim($session->os.' '.$session->os_version),
                    'Device' => collect([$session->device_type, $session->device_brand, $session->device_model])->filter()->implode(' · '),
                    'Screen' => $session->screen_width ? $session->screen_width.'×'.$session->screen_height.' @'.$session->pixel_ratio.'x' : null,
                    'Viewport' => $session->viewport_width ? $session->viewport_width.'×'.$session->viewport_height.' ('.$session->viewport_bucket.')' : null,
                    'Language' => $session->language,
                    'Timezone' => $session->timezone,
                    'Theme' => $session->color_scheme ? 'OS '.$session->color_scheme.', site '.$session->site_theme : null,
                    'Connection' => $session->connection,
                    'Hardware' => $session->cpu_cores ? $session->cpu_cores.' cores, '.$session->memory_gb.' GB' : null,
                    'User agent' => $session->user_agent,
                ] as $label => $value)
                    <div>
                        <dt class="text-xs text-subtle">{{ $label }}</dt>
                        <dd class="break-words">{{ $value ?: '–' }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-website.card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-website.card title="Events">
            @forelse ($session->events as $event)
                <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                    <span class="truncate">{{ $event->name }}@if ($event->label) <span class="text-muted">· {{ $event->label }}</span>@endif</span>
                    <span class="shrink-0 text-xs text-subtle">{{ $event->occurred_at->setTimezone($tz)->format('H:i:s') }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No events in this visit.</p>
            @endforelse
        </x-website.card>

        <x-website.card title="JavaScript errors">
            @forelse ($session->errors as $error)
                <a href="{{ route('dashboard.website.errors.show', $error->fingerprint) }}" class="block border-t border-border py-2 text-sm first:border-t-0 hover:text-brand">
                    <span class="block truncate text-negative">{{ $error->message }}</span>
                    <span class="text-xs text-subtle">{{ $error->path }} · {{ $error->occurred_at->setTimezone($tz)->format('H:i:s') }}</span>
                </a>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No errors in this visit.</p>
            @endforelse
        </x-website.card>
    </div>
</x-website.layout>
