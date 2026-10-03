@php
    $timezone = config('app.display_timezone');
    $timezoneLabel = now($timezone)->format('T');
    $formatDuration = function (int $seconds): string {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return number_format($hours).'h '.$minutes.'m';
        }

        return $minutes > 0 ? $minutes.'m' : ($seconds > 0 ? '< 1m' : '0m');
    };
    $dailyLabels = collect($usage['daily'])->pluck('label')->all();
    $dailyHours = collect($usage['daily'])->pluck('hours')->all();
@endphp

<x-dashboard-layout
    :title="$device->display_name"
    :subtitle="($device->device_name ? $device->short_id.' · ' : '').'Usage time, services and errors for this install.'"
>
    <x-slot:actions>
        <a href="{{ route('dashboard.devices') }}" class="text-sm text-muted hover:text-foreground">&larr; All devices</a>
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <x-dashboard.stat-tile label="Total usage" :value="$formatDuration($usage['total_seconds'])" />
        <x-dashboard.stat-tile label="Last 7 days" :value="$formatDuration($usage['last_7_days_seconds'])" />
        <x-dashboard.stat-tile label="Sessions" :value="number_format($usage['session_count'])" />
        <x-dashboard.stat-tile label="Avg session" :value="$formatDuration($usage['average_session_seconds'])" />
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-5 xl:col-span-2">
            <p class="text-sm font-medium">Daily usage</p>
            <p class="text-xs text-muted">Hours the app was open per day ({{ $timezoneLabel }}), last 30 days.</p>

            <div class="mt-4 h-64">
                <canvas
                    data-bar-chart
                    data-labels="{{ json_encode($dailyLabels) }}"
                    data-values="{{ json_encode($dailyHours) }}"
                ></canvas>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-sm font-medium uppercase tracking-wide text-subtle">System info</p>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-subtle">Name</dt>
                    <dd class="text-right text-foreground">{{ $device->device_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-subtle">Device ID</dt>
                    <dd class="break-all text-right font-mono text-xs text-foreground">{{ $device->device_id }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-subtle">OS</dt>
                    <dd class="text-right text-xs text-foreground">{{ $device->os ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-subtle">OS version</dt>
                    <dd class="text-right text-xs text-foreground">{{ $device->os_version ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-subtle">App version</dt>
                    <dd class="font-mono text-xs text-foreground">v{{ $device->app_version ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-subtle">Country</dt>
                    <dd class="text-right text-xs text-foreground">{{ $country ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-subtle">First seen</dt>
                    <dd class="text-right text-xs text-foreground">{{ $device->first_seen_at?->timezone($timezone)->format('d M Y H:i') ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-subtle">Last seen</dt>
                    <dd class="text-right text-xs text-foreground">{{ $device->last_seen_at?->timezone($timezone)->format('d M Y H:i') ?? 'never' }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-sm font-medium uppercase tracking-wide text-subtle">Services used</p>
            @if ($stack)
                <p class="mt-1 text-xs text-muted">
                    Current stack:
                    <span class="font-mono text-foreground">{{ collect([$stack['php_version'] ? 'PHP '.$stack['php_version'] : null, $stack['database_version']])->filter()->implode(' · ') ?: '—' }}</span>
                    <span class="text-subtle">(reported {{ $stack['reported_at']->diffForHumans() }})</span>
                </p>
            @endif

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                            <th class="py-2 pr-4 font-medium">Service</th>
                            <th class="py-2 pr-4 font-medium">Starts</th>
                            <th class="py-2 font-medium">Last started</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($services as $service)
                            <tr>
                                <td class="py-2.5 pr-4 font-medium text-foreground">{{ $service['service'] }}</td>
                                <td class="py-2.5 pr-4 text-muted">{{ number_format($service['starts']) }}</td>
                                <td class="py-2.5 text-xs text-subtle">{{ $service['last_started_at']->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-3 text-sm text-subtle" colspan="3">No services started from Rezure yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-sm font-medium uppercase tracking-wide text-subtle">Errors</p>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                            <th class="py-2 pr-4 font-medium">Error</th>
                            <th class="py-2 pr-4 font-medium">Occurrences</th>
                            <th class="py-2 font-medium">Last seen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($deviceErrors as $error)
                            <tr>
                                <td class="py-2.5 pr-4 font-mono text-xs text-foreground">{{ $error['error'] }}</td>
                                <td class="py-2.5 pr-4 text-muted">{{ number_format($error['occurrences']) }}</td>
                                <td class="py-2.5 text-xs text-subtle">{{ $error['last_seen_at']->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-3 text-sm text-subtle" colspan="3">No errors reported.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface">
        <div class="px-5 pt-5">
            <p class="text-sm font-medium uppercase tracking-wide text-subtle">Recent sessions</p>
            <p class="mt-1 text-xs text-muted">One session per app launch. A session the app never closed cleanly (crash, force-quit) is counted up to its last heartbeat.</p>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                        <th class="px-5 py-3 font-medium">Started ({{ $timezoneLabel }})</th>
                        <th class="px-5 py-3 font-medium">Duration</th>
                        <th class="px-5 py-3 font-medium">Version</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($recentSessions as $session)
                        <tr>
                            <td class="px-5 py-3 text-xs text-foreground">{{ $session->started_at->timezone($timezone)->format('d M Y H:i') }}</td>
                            <td class="px-5 py-3 text-muted">{{ $formatDuration($session->usage_seconds) }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-muted">v{{ $session->app_version }}</td>
                            <td class="px-5 py-3 text-xs">
                                @if ($session->ended_at)
                                    <span class="text-subtle">Closed</span>
                                @elseif ($session->last_heartbeat_at->gt(now()->subMinutes(10)))
                                    <span class="text-positive">Active</span>
                                @else
                                    <span class="text-subtle">Not closed cleanly</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-4 text-sm text-subtle" colspan="4">No sessions recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>
