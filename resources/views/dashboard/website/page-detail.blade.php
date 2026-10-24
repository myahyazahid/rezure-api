@php
    use App\Services\WebAnalytics\Reports\PerformanceReport;
    use App\Support\WebDisplay;

    $stats = $detail['compared']['current'];
    $change = $detail['compared']['change'];
    $chart = [
        'labels' => array_column($detail['trend'], 'label'),
        'series' => [
            'pageviews' => ['label' => 'Pageviews', 'format' => 'number', 'current' => array_column($detail['trend'], 'pageviews'), 'previous' => null],
            'visitors' => ['label' => 'Visitors', 'format' => 'number', 'current' => array_column($detail['trend'], 'visitors'), 'previous' => null],
        ],
        'default' => 'pageviews',
    ];
@endphp

<x-website.layout :title="$detail['path']" subtitle="One page in detail." :filters="$filters">
    <x-slot:actions>
        <a href="{{ route('dashboard.website.heatmap', [...$filters->query(), 'path' => $detail['path']]) }}" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-muted hover:text-foreground">Click heatmap</a>
        <a href="{{ route('dashboard.website.overview', $filters->queryWith('page', $detail['path'])) }}" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-muted hover:text-foreground">Filter dashboard by this page</a>
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        <x-website.kpi label="Pageviews" :value="number_format($stats['pageviews'])" :change="$change['pageviews']" />
        <x-website.kpi label="Visitors" :value="number_format($stats['visitors'])" :change="$change['visitors']" />
        <x-website.kpi label="Entries" :value="number_format($stats['entries'])" :change="$change['entries']" />
        <x-website.kpi label="Bounce" :value="WebDisplay::percent($stats['bounce_rate'])" :change="$change['bounce_rate']" :invert="true" />
        <x-website.kpi label="Exit rate" :value="WebDisplay::percent($stats['exit_rate'])" :change="$change['exit_rate']" :invert="true" />
        <x-website.kpi label="Engaged time" :value="WebDisplay::duration($stats['avg_engaged_seconds'])" :change="$change['avg_engaged_seconds']" />
        <x-website.kpi label="Avg. scroll" :value="WebDisplay::percent($stats['avg_scroll'], 0)" :change="$change['avg_scroll']" />
    </div>

    <x-website.card class="mt-4" data-web-timeseries-card>
        <x-slot:actions>
            @foreach ($chart['series'] as $metric => $series)
                <button type="button" data-web-metric="{{ $metric }}" @if ($metric === 'pageviews') data-active @endif class="rounded-md border border-border px-2.5 py-1 text-xs text-muted hover:text-foreground data-active:border-brand data-active:bg-brand/10 data-active:text-foreground">{{ $series['label'] }}</button>
            @endforeach
        </x-slot:actions>
        <div class="h-60"><canvas data-web-timeseries="{{ json_encode($chart) }}"></canvas></div>
    </x-website.card>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        @foreach (['previous' => 'Came from', 'next' => 'Went to next'] as $key => $title)
            <x-website.card :title="$title" :subtitle="$key === 'previous' ? 'The page viewed right before this one in the same visit.' : 'The page viewed right after.'">
                @forelse ($detail[$key] as $row)
                    <div class="relative flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                        <span class="absolute inset-y-1 left-0 rounded bg-brand/10" style="width: {{ $row['share'] }}%"></span>
                        @if (str_starts_with($row['path'], '('))
                            <span class="relative italic text-muted">{{ $row['path'] }}</span>
                        @else
                            <a href="{{ route('dashboard.website.pages.show', [...$filters->query(), 'path' => $row['path']]) }}" class="relative truncate hover:text-brand">{{ $row['path'] }}</a>
                        @endif
                        <span class="relative tabular-nums text-muted">{{ number_format($row['count']) }} · {{ WebDisplay::percent($row['share']) }}</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-subtle">No data for this period.</p>
                @endforelse
            </x-website.card>
        @endforeach

        <x-website.card title="Traffic sources to this page">
            <x-website.breakdown :rows="$detail['sources']" dimension="source" :filters="$filters" :columns="['visitors', 'sessions']" :drilldown="false" />
        </x-website.card>

        <x-website.card title="Scroll depth" subtitle="How far down this page visitors got.">
            <x-website.histogram :buckets="$detail['scroll']" height="h-48" />
        </x-website.card>

        <x-website.card title="Devices">
            <x-website.breakdown :rows="$detail['devices']" dimension="device_type" :filters="$filters" :columns="['visitors', 'sessions', 'bounce_rate']" :drilldown="false" />
        </x-website.card>

        <x-website.card title="Core Web Vitals (p75)">
            <div class="grid grid-cols-3 gap-3">
                @foreach (['lcp' => 'lcp_ms', 'inp' => 'inp_ms', 'cls' => 'cls'] as $key => $field)
                    @php
                        $value = $detail['vitals'][$key];
                        $rating = $value !== null ? app(PerformanceReport::class)->rating($field, $value) : null;
                    @endphp
                    <div class="rounded-lg bg-surface-raised p-3">
                        <p class="text-[11px] uppercase tracking-wide text-subtle">{{ PerformanceReport::VITALS[$field]['label'] }}</p>
                        <p @class(['mt-1 text-xl font-semibold tabular-nums', 'text-positive' => $rating === 'good', 'text-series-4' => $rating === 'needs', 'text-negative' => $rating === 'poor'])>
                            {{ $value === null ? '–' : ($field === 'cls' ? number_format($value, 3) : number_format($value).' ms') }}
                        </p>
                    </div>
                @endforeach
            </div>
        </x-website.card>
    </div>
</x-website.layout>
