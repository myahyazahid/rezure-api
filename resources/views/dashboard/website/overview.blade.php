@php
    use App\Support\WebDisplay;
    use Illuminate\Support\Arr;

    $current = $report['summary']['current'];
    $change = $report['summary']['change'];
@endphp

<x-website.layout title="Website analytics" subtitle="Visitors of rezure.redscale.my.id — where they come from, what they read, and who downloads." :filters="$filters">
    <x-slot:actions>
        <a href="{{ route('dashboard.website.realtime') }}" class="inline-flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm hover:border-brand/50">
            <span class="relative flex h-2 w-2">
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-positive opacity-60"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-positive"></span>
            </span>
            <span class="tabular-nums">{{ number_format($activeVisitors) }}</span>
            <span class="text-muted">online now</span>
        </a>
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        <x-website.kpi label="Unique visitors" :value="number_format($current['visitors'])" :change="$change['visitors']" :sparkline="$report['sparklines']['visitors']" />
        <x-website.kpi label="Sessions" :value="number_format($current['sessions'])" :change="$change['sessions']" :sparkline="$report['sparklines']['sessions']" />
        <x-website.kpi label="Pageviews" :value="number_format($current['pageviews'])" :change="$change['pageviews']" :sparkline="$report['sparklines']['pageviews']" />
        <x-website.kpi label="Downloads" :value="number_format($current['downloads'])" :change="$change['downloads']" :sparkline="$report['sparklines']['downloads']" />
        <x-website.kpi label="Download conversion" :value="WebDisplay::percent($current['conversion_rate'], 2)" :change="$change['conversion_rate']" hint="Sessions that downloaded the installer" />
        <x-website.kpi label="Pages / session" :value="number_format($current['views_per_session'], 2)" :change="$change['views_per_session']" />
        <x-website.kpi label="Bounce rate" :value="WebDisplay::percent($current['bounce_rate'])" :change="$change['bounce_rate']" :invert="true" hint="Sessions with under 10s engaged, one page and no download (GA4 definition)" />
        <x-website.kpi label="Visit duration" :value="WebDisplay::duration($current['avg_duration'])" :change="$change['avg_duration']" />
        <x-website.kpi label="Engaged time" :value="WebDisplay::duration($current['avg_engaged'])" :change="$change['avg_engaged']" hint="Average time per session with the tab visible and in use" />
        <x-website.kpi label="Engaged sessions" :value="number_format($current['engaged_sessions'])" :change="$change['engaged_sessions']" />
    </div>

    <x-website.card class="mt-4" data-web-timeseries-card>
        <x-slot:actions>
            @foreach ($report['chart']['series'] as $metric => $series)
                <button
                    type="button"
                    data-web-metric="{{ $metric }}"
                    @if ($metric === $report['chart']['default']) data-active @endif
                    class="rounded-md border border-border px-2.5 py-1 text-xs text-muted transition-colors hover:text-foreground data-active:border-brand data-active:bg-brand/10 data-active:text-foreground"
                >{{ $series['label'] }}</button>
            @endforeach
        </x-slot:actions>

        <div class="h-72">
            <canvas data-web-timeseries="{{ json_encode($report['chart']) }}"></canvas>
        </div>
        <p class="mt-3 text-xs text-subtle">
            Solid: {{ $filters->label() }}.
            @if ($filters->compare !== 'none') Dashed: {{ strtolower(\App\Services\WebAnalytics\WebAnalyticsFilters::COMPARISONS[$filters->compare]) }}. @endif
            Dashed markers: releases and blog posts published in the period.
        </p>
    </x-website.card>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-website.card title="Top pages">
            <x-slot:actions>
                <a href="{{ route('dashboard.website.pages', $filters->query()) }}" class="text-xs text-brand hover:underline">All pages →</a>
            </x-slot:actions>
            @include('dashboard.website.partials.pages-table', ['pages' => $report['pages'], 'compact' => true])
        </x-website.card>

        <x-website.card title="Landing pages" subtitle="Where visits start.">
            @include('dashboard.website.partials.pages-table', ['pages' => $report['entryPages'], 'compact' => true, 'entries' => true])
        </x-website.card>

        <x-website.card title="Channels">
            <x-slot:actions>
                <a href="{{ route('dashboard.website.acquisition', $filters->query()) }}" class="text-xs text-brand hover:underline">Acquisition →</a>
            </x-slot:actions>
            <x-website.breakdown :rows="$report['channels']" dimension="channel" :filters="$filters" :columns="['visitors', 'sessions', 'conversion_rate']" />
        </x-website.card>

        <x-website.card title="Sources">
            <x-website.breakdown :rows="$report['sources']" dimension="source" :filters="$filters" :columns="['visitors', 'sessions', 'bounce_rate']" />
        </x-website.card>

        <x-website.card title="Countries">
            <x-slot:actions>
                <a href="{{ route('dashboard.website.audience', $filters->query()) }}" class="text-xs text-brand hover:underline">Audience →</a>
            </x-slot:actions>
            <x-website.breakdown :rows="$report['countries']" dimension="country" :filters="$filters" :columns="['visitors', 'sessions', 'avg_duration']" />
        </x-website.card>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-website.card title="Devices">
                @if (count($report['devices']) > 0)
                    <div class="h-52">
                        <canvas data-share-doughnut="{{ json_encode(['labels' => array_map(fn ($row) => WebDisplay::value('device_type', $row['value']), $report['devices']), 'values' => array_column($report['devices'], 'sessions')]) }}"></canvas>
                    </div>
                @else
                    <p class="py-6 text-center text-xs text-subtle">No data for this period.</p>
                @endif
            </x-website.card>

            <x-website.card title="Top events">
                <x-slot:actions>
                    <a href="{{ route('dashboard.website.events', $filters->query()) }}" class="text-xs text-brand hover:underline">Events →</a>
                </x-slot:actions>
                @forelse ($report['events'] as $event)
                    <div class="flex items-center justify-between border-t border-border py-2 text-sm first:border-t-0">
                        <a href="{{ route('dashboard.website.events', [...$filters->query(), 'event' => $event['name']]) }}" class="truncate hover:text-brand">{{ $event['name'] }}</a>
                        <span class="tabular-nums text-muted">{{ number_format($event['total']) }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-subtle">No events yet.</p>
                @endforelse
            </x-website.card>
        </div>
    </div>
</x-website.layout>
