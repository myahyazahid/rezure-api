@php
    use App\Support\WebDisplay;
    use App\Services\WebAnalytics\WebDimensions;

    $groupTitles = ['search' => 'Search engines', 'social' => 'Social networks', 'developer' => 'Developer communities', 'ai' => 'AI assistants'];
@endphp

<x-website.layout title="Acquisition" subtitle="Where visits — and downloads — come from." :filters="$filters">
    <x-slot:actions>
        <a href="{{ route('dashboard.website.export', [...$filters->query(), 'report' => 'breakdown', 'dimension' => 'channel']) }}" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-muted hover:text-foreground">Export channels</a>
        <a href="{{ route('dashboard.website.export', [...$filters->query(), 'report' => 'breakdown', 'dimension' => 'source']) }}" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-muted hover:text-foreground">Export sources</a>
    </x-slot:actions>

    <div class="grid gap-4 lg:grid-cols-5">
        <x-website.card title="Channels" class="lg:col-span-3">
            <x-website.breakdown :rows="$report['channels']" dimension="channel" :filters="$filters" :columns="['visitors', 'sessions', 'bounce_rate', 'avg_duration', 'conversions', 'conversion_rate']" />
        </x-website.card>

        <x-website.card title="Sessions by channel" class="lg:col-span-2">
            <div class="h-64">
                <canvas data-multiline-chart data-labels="{{ json_encode($report['channelTrend']['labels']) }}" data-series="{{ json_encode($report['channelTrend']['series']) }}"></canvas>
            </div>
        </x-website.card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-website.card title="Sources">
            <x-website.breakdown :rows="$report['sources']" dimension="source" :filters="$filters" :columns="['visitors', 'sessions', 'bounce_rate', 'conversions']" />
        </x-website.card>

        <x-website.card title="Referring pages" subtitle="The exact pages that linked to the site.">
            @forelse ($report['referrers'] as $referrer)
                <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                    {{-- Visitor-supplied: only ever a link when it's http(s), never e.g. javascript://host/… --}}
                    @if (preg_match('#^https?://#i', $referrer['url']))
                        <a href="{{ $referrer['url'] }}" target="_blank" rel="noreferrer noopener" class="truncate hover:text-brand" title="{{ $referrer['url'] }}">{{ $referrer['url'] }}</a>
                    @else
                        <span class="truncate" title="{{ $referrer['url'] }}">{{ $referrer['url'] }}</span>
                    @endif
                    <span class="shrink-0 tabular-nums text-muted">{{ number_format($referrer['sessions']) }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No referrers in this period.</p>
            @endforelse
        </x-website.card>

        @foreach ($report['groups'] as $key => $group)
            <x-website.card :title="$groupTitles[$key]" :subtitle="$group['channel'].' channel'">
                <x-website.breakdown :rows="$group['rows']" dimension="source" :filters="$filters" :columns="['visitors', 'sessions', 'conversions']" />
            </x-website.card>
        @endforeach
    </div>

    <x-website.card title="Campaigns (UTM)" class="mt-4">
        @if (count($report['campaigns']) === 0)
            <p class="py-6 text-center text-xs text-subtle">No tagged visits in this period. Add <code class="rounded bg-surface-raised px-1">?utm_source=…&utm_campaign=…</code> to links you share.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                            @foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $column)
                                <th class="pb-2 font-medium">{{ WebDimensions::label($column) }}</th>
                            @endforeach
                            <th class="pb-2 text-right font-medium">Sessions</th>
                            <th class="pb-2 text-right font-medium">Visitors</th>
                            <th class="pb-2 text-right font-medium">Bounce</th>
                            <th class="pb-2 text-right font-medium">Downloads</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['campaigns'] as $campaign)
                            <tr class="border-t border-border">
                                @foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $column)
                                    <td class="max-w-40 truncate py-2 pr-3">
                                        @if ($campaign[$column] !== null)
                                            <a href="{{ request()->url() }}?{{ \Illuminate\Support\Arr::query($filters->queryWith($column, $campaign[$column])) }}" class="hover:text-brand">{{ $campaign[$column] }}</a>
                                        @else
                                            <span class="text-subtle">–</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($campaign['sessions']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($campaign['visitors']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::percent($campaign['bounce_rate']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($campaign['conversions']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-website.card>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-website.card title="Landing pages by channel">
            @forelse ($report['landingByChannel'] as $channel => $pages)
                <div class="border-t border-border py-2.5 first:border-t-0">
                    <p class="text-xs font-medium uppercase tracking-wide text-subtle">{{ WebDisplay::value('channel', $channel) }}</p>
                    @foreach ($pages as $page)
                        <div class="mt-1 flex items-center justify-between gap-3 text-sm">
                            <a href="{{ route('dashboard.website.pages.show', [...$filters->query(), 'path' => $page['path']]) }}" class="truncate hover:text-brand">{{ $page['path'] }}</a>
                            <span class="tabular-nums text-muted">{{ number_format($page['sessions']) }}</span>
                        </div>
                    @endforeach
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No data for this period.</p>
            @endforelse
        </x-website.card>

        <x-website.card title="Download attribution" subtitle="Last touch: the channel of the visit the download happened in. First touch: the channel the visitor originally arrived through.">
            @if (count($report['attribution']) === 0)
                <p class="py-6 text-center text-xs text-subtle">No downloads in this period.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                            <th class="pb-2 font-medium">Channel</th>
                            <th class="pb-2 text-right font-medium">Last touch</th>
                            <th class="pb-2 text-right font-medium">First touch</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['attribution'] as $row)
                            <tr class="border-t border-border">
                                <td class="py-2">{{ WebDisplay::value('channel', $row['channel']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($row['last_touch']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($row['first_touch']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-website.card>
    </div>
</x-website.layout>
