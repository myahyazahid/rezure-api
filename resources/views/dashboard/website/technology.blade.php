@php
    use App\Services\WebAnalytics\WebDimensions;

    $titles = [
        'browser' => 'Browsers', 'browser_version' => 'Browser versions', 'browser_engine' => 'Engines',
        'os' => 'Operating systems', 'os_version' => 'OS versions', 'device_type' => 'Device types',
        'device_brand' => 'Brands', 'device_model' => 'Models', 'screen' => 'Screen resolutions',
        'viewport' => 'Viewport sizes', 'connection' => 'Connection',
    ];
@endphp

<x-website.layout title="Technology" subtitle="The browsers, devices and screens the website is used on." :filters="$filters">
    <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
        @foreach ($report['breakdowns'] as $dimension => $rows)
            <x-website.card :title="$titles[$dimension] ?? WebDimensions::label($dimension)">
                <x-website.breakdown :rows="$rows" :dimension="$dimension" :filters="$filters" :columns="['visitors', 'share', 'bounce_rate']" />
            </x-website.card>
        @endforeach

        <x-website.card title="Color scheme" subtitle="OS preference vs the theme the site was shown in.">
            @forelse ($report['themes'] as $row)
                <div class="flex items-center justify-between border-t border-border py-2 text-sm first:border-t-0">
                    <span>OS <span class="font-medium">{{ $row['os'] }}</span> → site <span class="font-medium">{{ $row['site'] }}</span></span>
                    <span class="tabular-nums text-muted">{{ number_format($row['sessions']) }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No data for this period.</p>
            @endforelse
        </x-website.card>

        <x-website.card title="CPU cores">
            <x-website.histogram :buckets="$report['cpu']" height="h-44" />
        </x-website.card>

        <x-website.card title="Device memory">
            <x-website.histogram :buckets="$report['memory']" height="h-44" />
        </x-website.card>

        <x-website.card title="Pixel ratio">
            <x-website.histogram :buckets="$report['pixelRatio']" height="h-44" />
        </x-website.card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <x-website.card title="Bot traffic" :subtitle="number_format($report['bots']['total']).' hits dropped at ingest — never counted as visitors.'" class="lg:col-span-2">
            <x-website.histogram :buckets="array_map(fn ($label, $value) => ['label' => $label, 'count' => $value], $report['bots']['daily']['labels'], $report['bots']['daily']['values'])" height="h-56" />
        </x-website.card>

        <x-website.card title="Top bots">
            @forelse ($report['bots']['top'] as $bot)
                <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                    <span class="truncate">{{ $bot['name'] }} @if ($bot['category'])<span class="text-xs text-subtle">· {{ $bot['category'] }}</span>@endif</span>
                    <span class="tabular-nums text-muted">{{ number_format($bot['hits']) }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No bots in this period.</p>
            @endforelse
        </x-website.card>
    </div>
</x-website.layout>
