<x-dashboard-layout title="Technical" subtitle="OS/version footprint and runtime stack combinations, for testing and compatibility calls.">
    <x-slot:actions>
        <x-dashboard.period-tabs :period="$period" route="dashboard.technical" />
    </x-slot:actions>

    <div class="rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium uppercase tracking-wide text-subtle">OS breakdown</p>
        <p class="mt-1 text-xs text-muted">Exact os + os_version combination reported by each device.</p>

        <div class="mt-5 space-y-4">
            @forelse ($osVersions as $os)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium">{{ $os['label'] }}</span>
                        <span class="text-muted">{{ number_format($os['count']) }} devices · {{ $os['percentage'] }}%</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-surface-raised">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $os['percentage'] }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-subtle">No device data yet.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium uppercase tracking-wide text-subtle">Top combo stack</p>
        <p class="mt-1 text-xs text-muted">Most common PHP + MySQL/MariaDB version pairs, from service_started event metadata.</p>

        <div class="mt-5 space-y-4">
            @forelse ($stackCombos as $combo)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium">{{ $combo['label'] }}</span>
                        <span class="text-muted">{{ number_format($combo['count']) }} · {{ $combo['percentage'] }}%</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-surface-raised">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $combo['percentage'] }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-subtle">
                    No stack data yet — the desktop client doesn't currently report PHP/MySQL versions in the
                    service_started event's payload. This chart is ready to populate once it does
                    (expects <code class="rounded bg-surface-raised px-1 py-0.5 text-xs">payload.php_version</code> and
                    <code class="rounded bg-surface-raised px-1 py-0.5 text-xs">payload.mysql_version</code>).
                </p>
            @endforelse
        </div>
    </div>
</x-dashboard-layout>
