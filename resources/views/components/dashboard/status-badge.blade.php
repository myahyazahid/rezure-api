@props(['status'])
@php
    $styles = [
        'open' => 'bg-negative/15 text-negative',
        'in_progress' => 'bg-brand/15 text-brand',
        'resolved' => 'bg-positive/15 text-positive',
    ];
@endphp
<span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $styles[$status] ?? 'bg-surface-raised text-subtle' }}">
    {{ str($status)->replace('_', ' ')->headline() }}
</span>
