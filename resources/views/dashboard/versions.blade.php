<x-dashboard-layout title="Versions" subtitle="Which Rezure builds are actually installed right now.">
    <div class="rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium uppercase tracking-wide text-subtle">Version adoption</p>
        <p class="mt-1 text-xs text-muted">Based on each device's most recently reported app_version.</p>

        <div class="mt-5 space-y-4">
            @forelse ($versions as $i => $version)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2 font-medium">
                            v{{ $version['version'] }}
                            @if ($i === 0)
                                <span class="rounded-full bg-positive/15 px-2 py-0.5 text-[11px] font-medium text-positive">latest adopted</span>
                            @elseif ($version['percentage'] < 3)
                                <span class="rounded-full bg-negative/15 px-2 py-0.5 text-[11px] font-medium text-negative">candidate for deprecation</span>
                            @endif
                        </span>
                        <span class="text-muted">{{ number_format($version['count']) }} devices · {{ $version['percentage'] }}%</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-surface-raised">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $version['percentage'] }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-subtle">No device data yet.</p>
            @endforelse
        </div>
    </div>
</x-dashboard-layout>
