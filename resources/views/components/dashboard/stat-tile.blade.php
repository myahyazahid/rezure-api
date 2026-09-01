@props(['label', 'value', 'change' => null, 'changeLabel' => 'vs previous period'])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-surface p-5']) }}>
    <p class="text-xs font-medium uppercase tracking-wide text-subtle">{{ $label }}</p>
    <p class="mt-2 text-3xl font-semibold tracking-tight">{{ $value }}</p>

    @if (! is_null($change))
        <span class="mt-3 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $change >= 0 ? 'bg-positive/15 text-positive' : 'bg-negative/15 text-negative' }}">
            {{ $change >= 0 ? '+' : '' }}{{ $change }}%
        </span>
        <span class="ml-1 text-xs text-subtle">{{ $changeLabel }}</span>
    @endif
</div>
