<x-dashboard-layout title="Devices" subtitle="Individual installs — for debugging, not for regular monitoring.">
    <x-slot:actions>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <select
                name="version"
                onchange="this.form.submit()"
                class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-foreground"
            >
                <option value="">All versions</option>
                @foreach ($knownVersions as $version)
                    <option value="{{ $version }}" @selected($versionFilter === $version)>v{{ $version }}</option>
                @endforeach
            </select>
        </form>

        <a
            href="{{ route('dashboard.devices.export', ['version' => $versionFilter]) }}"
            class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90"
        >
            Export CSV
        </a>
    </x-slot:actions>

    <div class="rounded-xl border border-border bg-surface">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                        <th class="px-5 py-3 font-medium">Device</th>
                        <th class="px-5 py-3 font-medium">OS</th>
                        <th class="px-5 py-3 font-medium">Version</th>
                        <th class="px-5 py-3 font-medium">First seen</th>
                        <th class="px-5 py-3 font-medium">Last seen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($devices as $device)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs text-foreground">{{ $device->short_id }}</td>
                            <td class="px-5 py-3 text-muted">{{ $device->os ?? '—' }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-muted">v{{ $device->app_version ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-subtle">{{ $device->first_seen_at?->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-xs text-subtle">{{ $device->last_seen_at?->diffForHumans() ?? 'never' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-4 text-sm text-subtle" colspan="5">No devices registered yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $devices->links() }}
    </div>
</x-dashboard-layout>
