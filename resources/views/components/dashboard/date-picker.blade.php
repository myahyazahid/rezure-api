@props(['name', 'value' => null, 'label' => 'Select date'])

@php
    $display = $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y') : $label;
@endphp

<div class="relative" data-date-picker>
    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-date-picker-input>

    <button
        type="button"
        data-date-picker-toggle
        class="flex items-center gap-2 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-white hover:border-brand/50"
    >
        <span data-date-picker-label data-placeholder="{{ $label }}" class="{{ $value ? 'text-white' : 'text-subtle' }}">{{ $display }}</span>
    </button>

    <div
        data-date-picker-panel
        hidden
        class="absolute z-20 mt-2 w-64 rounded-xl border border-border bg-surface-raised p-3 shadow-xl"
    >
        <div class="flex items-center gap-2">
            <select data-date-picker-month class="flex-1 rounded-md border border-border bg-surface px-2 py-1 text-xs text-white focus:border-brand focus:outline-none"></select>
            <select data-date-picker-year class="rounded-md border border-border bg-surface px-2 py-1 text-xs text-white focus:border-brand focus:outline-none"></select>
        </div>

        <div class="mt-3 grid grid-cols-7 gap-y-1 text-center text-[10px] font-medium uppercase tracking-wide text-subtle">
            <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
        </div>
        <div data-date-picker-days class="grid grid-cols-7 gap-y-1 text-center text-xs"></div>

        <div class="mt-2 flex items-center justify-between border-t border-border pt-2">
            <button type="button" data-date-picker-clear class="text-xs text-muted hover:text-white">Clear</button>
            <button type="button" data-date-picker-today class="text-xs text-brand hover:underline">Today</button>
        </div>
    </div>
</div>
