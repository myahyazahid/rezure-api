<x-dashboard-layout title="Errors" subtitle="What's breaking, and for how many devices.">
    <x-slot:actions>
        <x-dashboard.period-tabs :period="$period" route="dashboard.errors" />
    </x-slot:actions>

    <div class="rounded-xl border border-border bg-surface">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                        <th class="px-5 py-3 font-medium">Error</th>
                        <th class="px-5 py-3 font-medium">Occurrences</th>
                        <th class="px-5 py-3 font-medium">Devices affected</th>
                        <th class="px-5 py-3 font-medium">Last seen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($errors as $error)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $error['error'] }}</td>
                            <td class="px-5 py-3 text-muted">{{ number_format($error['occurrences']) }}</td>
                            <td class="px-5 py-3 text-muted">{{ number_format($error['affected_devices']) }}</td>
                            <td class="px-5 py-3 text-xs text-subtle">{{ $error['last_seen_at']->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-4 text-sm text-subtle" colspan="4">No errors reported in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>
