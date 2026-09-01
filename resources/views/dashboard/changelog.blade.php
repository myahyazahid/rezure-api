<x-dashboard-layout title="Changelog" subtitle="Entries clients read from GET /api/v1/changelog.">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium uppercase tracking-wide text-subtle">
            {{ $editing ? 'Edit entry' : 'New entry' }}
        </p>

        <form
            method="POST"
            action="{{ $editing ? route('dashboard.changelog.update', $editing) : route('dashboard.changelog.store') }}"
            class="mt-4 space-y-4"
        >
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="version" class="mb-1 block text-xs font-medium text-muted">Version</label>
                    <input
                        type="text" name="version" id="version" value="{{ old('version', $editing?->version) }}"
                        placeholder="1.5.0"
                        class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-white placeholder:text-subtle focus:border-brand focus:outline-none"
                    >
                    @error('version')
                        <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="released_at" class="mb-1 block text-xs font-medium text-muted">Released at</label>
                    <input
                        type="datetime-local" name="released_at" id="released_at"
                        value="{{ old('released_at', $editing?->released_at?->format('Y-m-d\TH:i')) }}"
                        class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-white focus:border-brand focus:outline-none"
                    >
                    @error('released_at')
                        <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="title" class="mb-1 block text-xs font-medium text-muted">Title</label>
                <input
                    type="text" name="title" id="title" value="{{ old('title', $editing?->title) }}"
                    placeholder="Cloudflare tunnel support"
                    class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-white placeholder:text-subtle focus:border-brand focus:outline-none"
                >
                @error('title')
                    <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="body" class="mb-1 block text-xs font-medium text-muted">Body (markdown)</label>
                <textarea
                    name="body" id="body" rows="6"
                    placeholder="What changed..."
                    class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-white placeholder:text-subtle focus:border-brand focus:outline-none"
                >{{ old('body', $editing?->body) }}</textarea>
                @error('body')
                    <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand/90">
                    {{ $editing ? 'Save changes' : 'Publish entry' }}
                </button>

                @if ($editing)
                    <a href="{{ route('dashboard.changelog') }}" class="text-sm text-muted hover:text-white">Cancel edit</a>
                @endif
            </div>
        </form>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                    <th class="px-5 py-3 font-medium">Version</th>
                    <th class="px-5 py-3 font-medium">Title</th>
                    <th class="px-5 py-3 font-medium">Released</th>
                    <th class="px-5 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($changelogs as $entry)
                    <tr>
                        <td class="px-5 py-3 font-mono text-xs text-white">v{{ $entry->version }}</td>
                        <td class="max-w-md truncate px-5 py-3 text-muted">{{ $entry->title }}</td>
                        <td class="px-5 py-3 text-xs text-subtle">{{ $entry->released_at->diffForHumans() }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('dashboard.changelog', ['edit' => $entry->id]) }}" class="text-xs font-medium text-brand hover:underline">Edit</a>
                            <form method="POST" action="{{ route('dashboard.changelog.destroy', $entry) }}" class="ml-3 inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium text-negative hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-5 py-4 text-sm text-subtle" colspan="4">No changelog entries yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $changelogs->links() }}
    </div>
</x-dashboard-layout>
