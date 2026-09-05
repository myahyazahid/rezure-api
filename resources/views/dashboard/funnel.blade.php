<x-dashboard-layout title="Funnel" subtitle="Where devices drop off between install and week-one retention.">
    <div class="rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium uppercase tracking-wide text-subtle">Install → active after 7 days</p>
        <p class="mt-1 text-xs text-muted">
            {{ number_format($funnel['eligible_for_7_day_measurement']) }} devices are old enough (installed 7+ days ago) to measure the last stage.
        </p>

        <div class="mt-6 space-y-5">
            @foreach ($funnel['stages'] as $stage)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium">{{ $stage['label'] }}</span>
                        @if ($stage['tracked'])
                            <span class="text-muted">{{ number_format($stage['count']) }} devices · {{ $stage['percentage'] ?? 0 }}%</span>
                        @else
                            <span class="rounded-full bg-surface-raised px-2 py-0.5 text-[11px] font-medium text-subtle">not tracked</span>
                        @endif
                    </div>
                    <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full bg-surface-raised">
                        @if ($stage['tracked'])
                            <div class="h-full rounded-full bg-brand" style="width: {{ $stage['percentage'] ?? 0 }}%"></div>
                        @else
                            <div class="h-full w-full rounded-full border border-dashed border-border"></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <p class="mt-6 text-xs text-subtle">
            "Download" has no telemetry source in this API — a device only appears here once it has opened the app at least once.
            If download-stage conversion matters, source it separately (e.g. GitHub release download counts or website analytics)
            rather than from this dashboard.
        </p>
    </div>
</x-dashboard-layout>
