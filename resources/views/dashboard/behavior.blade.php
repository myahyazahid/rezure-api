@php
    $sessionLabels = collect($sessionLengths)->pluck('label')->all();
    $sessionValues = collect($sessionLengths)->pluck('count')->all();
    $trendLabels = collect($newVsReturning)->map(fn ($p) => \Illuminate\Support\Carbon::parse($p['date'])->format('d M'))->all();
    $newValues = collect($newVsReturning)->pluck('new')->all();
    $returningValues = collect($newVsReturning)->pluck('returning')->all();
@endphp

<x-dashboard-layout title="User Behavior" subtitle="Session patterns, retention, and devices going quiet.">
    <x-slot:actions>
        <x-dashboard.period-tabs :period="$period" route="dashboard.behavior" />
    </x-slot:actions>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-dashboard.stat-tile
            label="Possibly uninstalled"
            :value="number_format($churn['count'])"
            :change="null"
        />
        <div class="rounded-xl border border-border bg-surface p-5 sm:col-span-2">
            <p class="text-xs font-medium uppercase tracking-wide text-subtle">Churn indicator</p>
            <p class="mt-2 text-sm text-muted">
                {{ number_format($churn['count']) }} devices ({{ $churn['percentage'] }}%) haven't reported in over
                {{ $churn['threshold_days'] }} days — likely uninstalled or dormant.
            </p>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-sm font-medium">Session length distribution</p>
            <p class="text-xs text-muted">Completed sessions, bucketed by duration.</p>

            <div class="mt-4 h-64">
                <canvas
                    data-bar-chart
                    data-labels="{{ json_encode($sessionLabels) }}"
                    data-values="{{ json_encode($sessionValues) }}"
                ></canvas>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-sm font-medium">New vs returning devices</p>
            <p class="text-xs text-muted">Daily breakdown of first-time vs already-known devices.</p>

            <div class="mt-4 h-64">
                <canvas
                    data-stacked-bar-chart
                    data-labels="{{ json_encode($trendLabels) }}"
                    data-new-values="{{ json_encode($newValues) }}"
                    data-returning-values="{{ json_encode($returningValues) }}"
                ></canvas>
            </div>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium uppercase tracking-wide text-subtle">Cohort retention</p>
        <p class="mt-1 text-xs text-muted">Of the devices first seen in a given week, the % still active N weeks later.</p>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                        <th class="py-2 pr-4 font-medium">Cohort week</th>
                        <th class="py-2 pr-4 font-medium">Size</th>
                        @for ($i = 0; $i <= $cohorts['maxOffset']; $i++)
                            <th class="px-1.5 py-2 text-center font-medium">W{{ $i }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($cohorts['cohorts'] as $cohort)
                        <tr>
                            <td class="py-2.5 pr-4 whitespace-nowrap text-foreground">{{ $cohort['label'] }}</td>
                            <td class="py-2.5 pr-4 text-muted">{{ number_format($cohort['size']) }}</td>
                            @foreach ($cohort['retention'] as $value)
                                <td class="px-1.5 py-2.5 text-center">
                                    @if (! is_null($value))
                                        <span
                                            class="heatmap-cell inline-flex h-8 w-12 items-center justify-center rounded-md text-xs font-medium text-foreground"
                                            style="--intensity: {{ $value / 100 }}"
                                        >{{ $value }}%</span>
                                    @else
                                        <span class="text-subtle">—</span>
                                    @endif
                                </td>
                            @endforeach
                            @for ($i = count($cohort['retention']); $i <= $cohorts['maxOffset']; $i++)
                                <td class="px-1.5 py-2.5 text-center text-subtle">—</td>
                            @endfor
                        </tr>
                    @empty
                        <tr>
                            <td class="py-4 text-sm text-subtle" colspan="{{ 2 + $cohorts['maxOffset'] + 1 }}">Not enough history yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface">
        <p class="px-5 pt-5 text-sm font-medium uppercase tracking-wide text-subtle">Quiet devices</p>
        <table class="mt-3 w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                    <th class="px-5 py-3 font-medium">Device</th>
                    <th class="px-5 py-3 font-medium">Last seen</th>
                    <th class="px-5 py-3 font-medium">Days silent</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($churn['devices'] as $device)
                    <tr>
                        <td class="px-5 py-3 font-mono text-xs text-foreground">{{ $device->short_id }}</td>
                        <td class="px-5 py-3 text-xs text-subtle">{{ $device->last_seen_at?->format('d M Y') ?? 'never' }}</td>
                        <td class="px-5 py-3 text-muted">{{ $device->last_seen_at?->diffInDays(now()) ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-5 py-4 text-sm text-subtle" colspan="3">No quiet devices — everyone's still checking in.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-dashboard-layout>
