<x-dashboard-layout title="Releases" subtitle="What's published as the current version — backs GET /api/v1/version/latest.">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="rounded-xl border border-border bg-surface p-5 xl:col-span-2">
            <p class="text-sm font-medium uppercase tracking-wide text-subtle">Publish a release</p>
            <p class="mt-1 text-xs text-muted">
                Clients calling <code class="text-white">GET /api/v1/version/latest</code> see this immediately after submit.
            </p>

            <form method="POST" action="{{ route('dashboard.releases.store') }}" class="mt-4 space-y-4">
                @csrf

                <div>
                    <label for="version" class="mb-1 block text-xs font-medium text-muted">Version</label>
                    <input
                        type="text" name="version" id="version" value="{{ old('version') }}"
                        placeholder="1.5.0"
                        class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-white placeholder:text-subtle focus:border-brand focus:outline-none"
                    >
                    @error('version')
                        <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="notes" class="mb-1 block text-xs font-medium text-muted">Changelog notes (optional)</label>
                    <textarea
                        name="notes" id="notes" rows="3"
                        placeholder="What changed in this release..."
                        class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-white placeholder:text-subtle focus:border-brand focus:outline-none"
                    >{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand/90">
                    Publish
                </button>
            </form>
        </div>

        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="text-sm font-medium uppercase tracking-wide text-subtle">Current release</p>

            @if ($current)
                <p class="mt-2 text-2xl font-semibold tracking-tight">v{{ $current->version }}</p>
                <p class="mt-1 text-xs text-subtle">published {{ $current->published_at->diffForHumans() }}</p>
                @if ($current->notes)
                    <p class="mt-3 whitespace-pre-line text-sm text-muted">{{ $current->notes }}</p>
                @endif
            @else
                <p class="mt-2 text-sm text-subtle">Nothing published yet — clients calling version/latest get null fields.</p>
            @endif
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                    <th class="px-5 py-3 font-medium">Version</th>
                    <th class="px-5 py-3 font-medium">Notes</th>
                    <th class="px-5 py-3 font-medium">Published</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($releases as $release)
                    <tr>
                        <td class="px-5 py-3 font-mono text-xs text-white">v{{ $release->version }}</td>
                        <td class="max-w-md truncate px-5 py-3 text-muted">{{ $release->notes ?? '—' }}</td>
                        <td class="px-5 py-3 text-xs text-subtle">{{ $release->published_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-5 py-4 text-sm text-subtle" colspan="3">No releases published yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $releases->links() }}
    </div>
</x-dashboard-layout>
