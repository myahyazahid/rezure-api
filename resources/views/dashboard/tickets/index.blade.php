<x-dashboard-layout title="Tickets" subtitle="Bug reports, feature requests, and general feedback submitted from clients.">
    <x-slot:actions>
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <select
                name="status"
                onchange="this.form.submit()"
                class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-foreground"
            >
                <option value="">All statuses</option>
                @foreach (['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'] as $value => $label)
                    <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select
                name="category"
                onchange="this.form.submit()"
                class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-foreground"
            >
                <option value="">All categories</option>
                @foreach (['bug' => 'Bug', 'feature_request' => 'Feature request', 'general' => 'General'] as $value => $label)
                    <option value="{{ $value }}" @selected($categoryFilter === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <x-dashboard.date-picker name="from" :value="$fromFilter" label="From date" />
            <span class="text-xs text-subtle">to</span>
            <x-dashboard.date-picker name="to" :value="$toFilter" label="To date" />

            @if ($statusFilter || $categoryFilter || $fromFilter || $toFilter)
                <a href="{{ route('dashboard.tickets') }}" class="text-sm text-muted hover:text-foreground">Clear</a>
            @endif
        </form>

        <a
            href="{{ route('dashboard.tickets.export', request()->only('status', 'category', 'from', 'to')) }}"
            class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90"
        >
            Export CSV
        </a>
    </x-slot:actions>

    <div class="rounded-xl border border-border bg-surface">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                    <th class="px-5 py-3 font-medium">Title</th>
                    <th class="px-5 py-3 font-medium">Category</th>
                    <th class="px-5 py-3 font-medium">Status</th>
                    <th class="px-5 py-3 font-medium">Device</th>
                    <th class="px-5 py-3 font-medium">Submitted</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($tickets as $ticket)
                    <tr>
                        <td class="px-5 py-3 text-foreground">
                            <a href="{{ route('dashboard.tickets.show', $ticket) }}" class="hover:underline">{{ $ticket->title }}</a>
                        </td>
                        <td class="px-5 py-3 text-muted">{{ str($ticket->category)->replace('_', ' ')->headline() }}</td>
                        <td class="px-5 py-3"><x-dashboard.status-badge :status="$ticket->status" /></td>
                        <td class="px-5 py-3 font-mono text-xs text-muted">{{ $ticket->device?->short_id ?? '—' }}</td>
                        <td class="px-5 py-3 text-xs text-subtle">{{ $ticket->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-5 py-4 text-sm text-subtle" colspan="5">No tickets match this filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $tickets->links() }}
    </div>
</x-dashboard-layout>
