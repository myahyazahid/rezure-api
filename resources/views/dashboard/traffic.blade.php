@php
    $maxHourTotal = collect($heatmap)->max('total');
    $dayLabels = collect($byDayOfWeek)->pluck('day')->all();
    $dayValues = collect($byDayOfWeek)->pluck('total')->all();
@endphp

<x-dashboard-layout title="Traffic Insight" subtitle="When devices are actually active — by hour and by weekday.">
    <x-slot:actions>
        <x-dashboard.period-tabs :period="$period" route="dashboard.traffic" />
    </x-slot:actions>

    <div class="rounded-xl border border-border bg-surface p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium">Traffic by hour</p>
                <p class="text-xs text-muted">Total events per hour of day, summed over the selected period.</p>
            </div>
            <p class="text-xs text-subtle">{{ number_format($maxHourTotal) }} at peak</p>
        </div>

        <div class="mt-5 grid grid-cols-12 gap-1.5 sm:grid-cols-[repeat(24,minmax(0,1fr))]">
            @foreach ($heatmap as $cell)
                <div
                    class="heatmap-cell aspect-square rounded-md"
                    style="--intensity: {{ $cell['intensity'] }}"
                    title="{{ sprintf('%02d:00', $cell['hour']) }} — {{ number_format($cell['total']) }} events"
                ></div>
            @endforeach
        </div>
        <div class="mt-1.5 grid grid-cols-12 gap-1.5 sm:grid-cols-[repeat(24,minmax(0,1fr))]">
            @foreach ($heatmap as $cell)
                <p class="text-center text-[9px] text-subtle">{{ $cell['hour'] }}</p>
            @endforeach
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium">Traffic by day of week</p>
        <p class="text-xs text-muted">Weekday vs weekend usage pattern.</p>

        <div class="mt-4 h-64">
            <canvas
                data-bar-chart
                data-labels="{{ json_encode($dayLabels) }}"
                data-values="{{ json_encode($dayValues) }}"
            ></canvas>
        </div>
    </div>
</x-dashboard-layout>
