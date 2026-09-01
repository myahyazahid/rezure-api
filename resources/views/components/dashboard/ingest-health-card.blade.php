@props(['class' => ''])

@php
    $health = app(\App\Services\DashboardMetricsService::class)->ingestHealth();
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-surface-raised p-3 '.$class]) }}>
    <div class="flex items-center gap-2">
        <span class="h-2 w-2 rounded-full {{ $health['healthy'] ? 'bg-positive' : 'bg-negative' }}"></span>
        <span class="text-sm font-medium">{{ $health['healthy'] ? 'Ingest healthy' : 'No recent ingest' }}</span>
    </div>
    <p class="mt-1 text-xs text-muted">
        @if ($health['last_event_at'])
            last event {{ $health['last_event_at']->diffForHumans() }}
        @else
            no events recorded yet
        @endif
    </p>
</div>
