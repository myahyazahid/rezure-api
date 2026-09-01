@php
    $trendLabels = collect($trend)->map(fn ($point) => \Illuminate\Support\Carbon::parse($point['date'])->format('d M'))->all();
    $trendValues = collect($trend)->pluck('count')->all();
@endphp

<x-dashboard-layout title="Overview" subtitle="Install base and activity across every reporting device.">
    <x-slot:actions>
        <x-dashboard.period-tabs :period="$period" route="dashboard.overview" />
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-dashboard.stat-tile
            label="Daily active"
            :value="number_format($activeUsers['dau'])"
            :change="$activeUsers['dau_change']"
            change-label="vs previous day"
        />
        <x-dashboard.stat-tile
            label="Weekly active"
            :value="number_format($activeUsers['wau'])"
            :change="$activeUsers['wau_change']"
            change-label="vs previous week"
        />
        <x-dashboard.stat-tile
            label="Monthly active"
            :value="number_format($activeUsers['mau'])"
            :change="$activeUsers['mau_change']"
            change-label="vs previous month"
        />
        <x-dashboard.stat-tile
            label="Registered devices"
            :value="number_format($registeredDevices['total'])"
            :change="$registeredDevices['new_change']"
            :change-label="'+'.number_format($registeredDevices['new_in_period']).' new this period'"
        />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-5 xl:col-span-2">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium">Active devices</p>
                    <p class="text-xs text-muted">Daily unique device_id reporting in</p>
                </div>
            </div>
            <div class="mt-4 h-64">
                <canvas
                    data-trend-chart
                    data-labels="{{ json_encode($trendLabels) }}"
                    data-values="{{ json_encode($trendValues) }}"
                ></canvas>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-sm font-medium">Stickiness</p>
            <p class="text-xs text-muted">DAU over MAU</p>

            <div class="relative mx-auto mt-4 h-36 w-36">
                <canvas data-doughnut-chart data-value="{{ $activeUsers['stickiness'] ?? 0 }}"></canvas>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-2xl font-semibold">{{ $activeUsers['stickiness'] ?? '—' }}%</span>
                    <span class="text-[11px] text-subtle">DAU/MAU</span>
                </div>
            </div>

            <div class="mt-6 space-y-3">
                @forelse ($osBreakdown as $os)
                    <div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-muted">{{ $os['os'] }}</span>
                            <span class="text-subtle">{{ $os['percentage'] }}%</span>
                        </div>
                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-surface-raised">
                            <div class="h-full rounded-full bg-brand" style="width: {{ $os['percentage'] }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-subtle">No device data yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium uppercase tracking-wide text-subtle">Recent ingest</p>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <tbody class="divide-y divide-border">
                    @forelse ($recentIngest as $event)
                        <tr>
                            <td class="whitespace-nowrap py-2.5 pr-4 font-mono text-xs text-subtle">
                                {{ $event->occurred_at->format('H:i:s') }}
                            </td>
                            <td class="py-2.5 pr-4">
                                <span class="text-white">{{ $event->event_type }}</span>
                                @if ($event->event_name)
                                    <span class="text-muted">· {{ $event->event_name }}</span>
                                @endif
                            </td>
                            <td class="py-2.5 pr-4 font-mono text-xs text-muted">{{ $event->device?->short_id ?? '—' }}</td>
                            <td class="py-2.5 text-right font-mono text-xs text-subtle">v{{ $event->app_version }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-4 text-sm text-subtle">No events ingested yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>
