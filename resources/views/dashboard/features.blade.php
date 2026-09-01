<x-dashboard-layout title="Features" subtitle="What people actually do once Rezure is open.">
    <x-slot:actions>
        <x-dashboard.period-tabs :period="$period" route="dashboard.features" />
    </x-slot:actions>

    <div class="rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium uppercase tracking-wide text-subtle">Feature usage</p>
        <p class="mt-1 text-xs text-muted">Event volume by type, excluding error reports.</p>

        <div class="mt-5 space-y-3">
            @forelse ($features as $feature)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium">
                            {{ $feature['event_type'] }}
                            @if ($feature['event_name'])
                                <span class="text-muted">· {{ $feature['event_name'] }}</span>
                            @endif
                        </span>
                        <span class="text-muted">{{ number_format($feature['count']) }} · {{ $feature['percentage'] }}%</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-surface-raised">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $feature['percentage'] }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-subtle">No feature events in this period.</p>
            @endforelse
        </div>
    </div>
</x-dashboard-layout>
