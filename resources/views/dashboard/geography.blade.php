@php
    $growthLabels = $growth['labels'];
    $growthSeries = collect($growth['series'])->map(fn ($s) => ['name' => $s['name'], 'data' => $s['data']])->values();
@endphp

<x-dashboard-layout title="Geography" subtitle="Where reporting devices are located, by country.">
    <x-slot:actions>
        <x-dashboard.period-tabs :period="$period" route="dashboard.geography" />
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-sm font-medium uppercase tracking-wide text-subtle">Geographic distribution</p>
            <p class="mt-1 text-xs text-muted">Devices seen per country over the selected period, most first.</p>

            <div class="mt-5 space-y-4">
                @forelse ($distribution as $country)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ $country['country_name'] }}</span>
                            <span class="text-muted">{{ number_format($country['total']) }} devices · {{ $country['percentage'] }}%</span>
                        </div>
                        <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-surface-raised">
                            <div class="h-full rounded-full bg-brand" style="width: {{ $country['percentage'] }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-subtle">No geolocated traffic yet.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-sm font-medium">Growth per region</p>
            <p class="text-xs text-muted">Daily device count for the top {{ $growthSeries->count() }} countries.</p>

            <div class="mt-4 h-72">
                @if ($growthSeries->isNotEmpty())
                    <canvas
                        data-multiline-chart
                        data-labels="{{ json_encode($growthLabels) }}"
                        data-series="{{ json_encode($growthSeries) }}"
                    ></canvas>
                @else
                    <p class="text-sm text-subtle">No geolocated traffic yet.</p>
                @endif
            </div>
        </div>
    </div>
</x-dashboard-layout>
