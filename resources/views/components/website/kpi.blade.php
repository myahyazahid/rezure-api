@props(['label', 'value', 'change' => null, 'invert' => false, 'sparkline' => null, 'hint' => null])

@php
    // For a metric like bounce rate a rise is bad news — `invert` flips the colour.
    $isGood = $change === null ? null : ($invert ? $change <= 0 : $change >= 0);
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col rounded-xl border border-border bg-surface p-4']) }}>
    <p class="text-[11px] font-medium uppercase tracking-wide text-subtle" @if ($hint) title="{{ $hint }}" @endif>{{ $label }}</p>
    <p class="mt-1.5 text-2xl font-semibold tracking-tight tabular-nums">{{ $value }}</p>

    <div class="mt-1 h-4">
        @if ($change !== null)
            <span @class([
                'text-xs font-medium',
                'text-positive' => $isGood,
                'text-negative' => ! $isGood,
            ])>{{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}%</span>
        @endif
    </div>

    @if ($sparkline !== null)
        <div class="mt-2 h-8">
            <canvas data-sparkline="{{ json_encode(array_values($sparkline)) }}" aria-hidden="true"></canvas>
        </div>
    @endif
</div>
