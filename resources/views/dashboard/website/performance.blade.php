@php
    $format = fn (string $field, ?float $value): string => $value === null ? '–' : ($field === 'cls' ? number_format($value, 3) : number_format($value).' ms');
    $tone = fn (?string $rating): string => match ($rating) {
        'good' => 'text-positive',
        'needs' => 'text-series-4',
        'poor' => 'text-negative',
        default => 'text-muted',
    };
@endphp

<x-website.layout title="Performance" :subtitle="'Core Web Vitals measured in real visitors\' browsers (p75) — '.number_format($report['samples']).' measured pageviews.'" :filters="$filters">
    <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
        @foreach ($report['overall'] as $field => $vital)
            <x-website.card>
                <p class="text-[11px] font-medium uppercase tracking-wide text-subtle">{{ $vital['label'] }} <span class="normal-case">p75</span></p>
                <p class="mt-1.5 text-2xl font-semibold tabular-nums {{ $tone($vital['rating']) }}">{{ $format($field, $vital['p75']) }}</p>
                <div class="mt-3 flex h-1.5 overflow-hidden rounded-full bg-surface-raised" title="Good {{ $vital['distribution']['good'] }}% · Needs improvement {{ $vital['distribution']['needs'] }}% · Poor {{ $vital['distribution']['poor'] }}%">
                    <div class="bg-positive" style="width: {{ $vital['distribution']['good'] }}%"></div>
                    <div class="bg-series-4" style="width: {{ $vital['distribution']['needs'] }}%"></div>
                    <div class="bg-negative" style="width: {{ $vital['distribution']['poor'] }}%"></div>
                </div>
                <p class="mt-1.5 text-[11px] text-subtle">good ≤ {{ $field === 'cls' ? $thresholds[$field]['good'] : number_format($thresholds[$field]['good']).' ms' }}</p>
            </x-website.card>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <x-website.card title="LCP & INP over time (daily p75)" class="lg:col-span-2">
            <div class="h-64">
                <canvas data-multiline-chart data-labels="{{ json_encode($report['trend']['labels']) }}" data-series="{{ json_encode($report['trend']['series']) }}"></canvas>
            </div>
        </x-website.card>

        <x-website.card title="By device type">
            @forelse ($report['byDevice'] as $device => $data)
                <div class="border-t border-border py-2 first:border-t-0">
                    <p class="text-sm font-medium capitalize">{{ $device }} <span class="text-xs font-normal text-subtle">· {{ number_format($data['samples']) }}</span></p>
                    <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs">
                        @foreach (['lcp_ms', 'inp_ms', 'cls'] as $field)
                            <span class="{{ $tone($data['vitals'][$field]['rating']) }}">{{ $data['vitals'][$field]['label'] }} {{ $format($field, $data['vitals'][$field]['p75']) }}</span>
                        @endforeach
                    </p>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No measurements in this period.</p>
            @endforelse
        </x-website.card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <x-website.card title="By page" class="lg:col-span-2">
            @if (count($report['byPage']) === 0)
                <p class="py-6 text-center text-xs text-subtle">No measurements in this period.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                                <th class="pb-2 font-medium">Page</th>
                                <th class="pb-2 text-right font-medium">Samples</th>
                                @foreach (['LCP', 'INP', 'CLS', 'TTFB'] as $label)
                                    <th class="pb-2 text-right font-medium">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($report['byPage'] as $page)
                                <tr class="border-t border-border">
                                    <td class="max-w-0 py-2 pr-3"><a href="{{ route('dashboard.website.pages.show', [...$filters->query(), 'path' => $page['path']]) }}" class="block truncate hover:text-brand">{{ $page['path'] }}</a></td>
                                    <td class="py-2 text-right tabular-nums text-muted">{{ number_format($page['samples']) }}</td>
                                    @foreach (['lcp_ms', 'inp_ms', 'cls', 'ttfb_ms'] as $field)
                                        <td class="py-2 text-right tabular-nums {{ $tone($page['vitals'][$field]['rating']) }}">{{ $format($field, $page['vitals'][$field]['p75']) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-website.card>

        <x-website.card title="Slowest pages" subtitle="p75 full page load, pages with 3+ measurements.">
            @forelse ($report['slowest'] as $page)
                <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                    <span class="truncate">{{ $page['path'] }}</span>
                    <span class="shrink-0 tabular-nums text-muted">{{ number_format($page['page_load']) }} ms</span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">Not enough measurements yet.</p>
            @endforelse
        </x-website.card>
    </div>
</x-website.layout>
