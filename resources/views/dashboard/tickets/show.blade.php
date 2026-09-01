<x-dashboard-layout :title="$ticket->title" subtitle="Submitted {{ $ticket->created_at->diffForHumans() }} by device {{ $ticket->device?->short_id ?? '—' }}">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-5 xl:col-span-2">
            <div class="flex items-center gap-2">
                <x-dashboard.status-badge :status="$ticket->status" />
                <span class="text-xs text-subtle">{{ str($ticket->category)->replace('_', ' ')->headline() }}</span>
            </div>

            <p class="mt-4 whitespace-pre-line text-sm text-muted">{{ $ticket->description }}</p>

            <div class="mt-6">
                <p class="text-sm font-medium uppercase tracking-wide text-subtle">Attachments</p>

                @if ($ticket->attachments->isEmpty())
                    <p class="mt-2 text-sm text-subtle">No attachments.</p>
                @else
                    <ul class="mt-2 space-y-2">
                        @foreach ($ticket->attachments as $attachment)
                            <li class="flex items-center justify-between rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm">
                                <span class="truncate text-white">{{ $attachment->file_name }}</span>
                                <a
                                    href="{{ route('dashboard.tickets.attachments.download', [$ticket, $attachment]) }}"
                                    class="ml-3 shrink-0 text-xs font-medium text-brand hover:underline"
                                >
                                    Download ({{ number_format($attachment->file_size / 1024, 1) }} KB)
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-border bg-surface p-5">
                <p class="text-sm font-medium uppercase tracking-wide text-subtle">System info</p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-subtle">App version</dt>
                        <dd class="font-mono text-xs text-white">{{ $ticket->app_version ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-subtle">OS version</dt>
                        <dd class="text-xs text-white">{{ $ticket->os_version ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-subtle">Device</dt>
                        <dd class="font-mono text-xs text-white">{{ $ticket->device?->short_id ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl border border-border bg-surface p-5">
                <p class="text-sm font-medium uppercase tracking-wide text-subtle">Update status</p>
                <form method="POST" action="{{ route('dashboard.tickets.update', $ticket) }}" class="mt-3 space-y-3">
                    @csrf
                    @method('PATCH')

                    <select name="status" class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-white">
                        @foreach (['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved'] as $value => $label)
                            <option value="{{ $value }}" @selected($ticket->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>

                    <button type="submit" class="w-full rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand/90">
                        Save
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
