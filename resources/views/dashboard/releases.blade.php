<x-dashboard-layout title="Releases" subtitle="The current version of each major line — backs GET /api/v1/version/latest and /version/upgrade.">
    <x-slot:actions>
        <button
            type="button"
            onclick="const f = document.getElementById('release-form'); f.open = true; f.scrollIntoView({behavior: 'smooth', block: 'start'});"
            class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90"
        >
            + Publish release
        </button>
    </x-slot:actions>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium uppercase tracking-wide text-subtle">Current release per line</p>
        <p class="mt-1 text-xs text-muted">
            A client is only offered updates from its own major line — a 3.x install gets 3.x releases and never auto-updates to 4.0.
        </p>

        @if ($lines->isNotEmpty())
            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($lines as $line)
                    <div class="rounded-lg border border-border bg-surface-raised p-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-subtle">{{ $line->major }}.x</p>
                        <p class="mt-1 text-2xl font-semibold tracking-tight">v{{ $line->version }}</p>
                        <p class="mt-1 text-xs text-subtle">published {{ $line->published_at->diffForHumans() }}</p>
                        @if ($line->notes)
                            <p class="mt-3 whitespace-pre-line text-sm text-muted">{{ $line->notes }}</p>
                        @endif
                        <p class="mt-3 text-xs {{ $line->signature && $line->download_url ? 'text-positive' : 'text-subtle' }}">
                            @if ($line->signature && $line->download_url)
                                Windows updater manifest attached — auto-update will offer this release to {{ $line->major }}.x installs.
                            @else
                                No signed installer attached — auto-update won't offer this release, only /changelog and /version/latest's plain fields see it.
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @else
            <p class="mt-2 text-sm text-subtle">Nothing published yet — clients calling version/latest get 204.</p>
        @endif
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface p-5">
        <form method="POST" action="{{ route('dashboard.releases.upgrade-notice.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <p class="text-sm font-medium uppercase tracking-wide text-subtle">Upgrade notice</p>
                <p class="mt-1 text-xs text-muted">
                    A banner on the Changelog page of clients on an older major line, linking to the website — backs
                    <code class="text-foreground">GET /api/v1/version/upgrade</code>. It never installs anything; moving to a new major stays the user's call.
                </p>
            </div>

            <label class="flex items-center gap-2 text-sm text-foreground">
                <input type="hidden" name="enabled" value="0">
                <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $notice->enabled)) class="rounded border-border bg-surface-raised">
                Show the notice
            </label>

            <div>
                <label for="major" class="mb-1 block text-xs font-medium text-muted">Announced major</label>
                <p class="mb-1 text-xs text-subtle">Clients on a lower major see the notice — <code class="text-foreground">4</code> reaches every 3.x and older install.</p>
                <input
                    type="number" name="major" id="major" min="1" value="{{ old('major', $notice->major) }}"
                    placeholder="4"
                    class="w-32 rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                >
                @error('major', 'upgradeNotice')
                    <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="notice-message" class="mb-1 block text-xs font-medium text-muted">Message</label>
                <textarea
                    name="message" id="notice-message" rows="2"
                    placeholder="Rezure 4 is out — see what's new."
                    class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                >{{ old('message', $notice->message) }}</textarea>
                @error('message', 'upgradeNotice')
                    <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="notice-url" class="mb-1 block text-xs font-medium text-muted">Link</label>
                <input
                    type="text" name="url" id="notice-url" value="{{ old('url', $notice->url) }}"
                    placeholder="https://.../download"
                    class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                >
                @error('url', 'upgradeNotice')
                    <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90">
                Save notice
            </button>
        </form>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                        <th class="px-5 py-3 font-medium">Version</th>
                        <th class="px-5 py-3 font-medium">Notes</th>
                        <th class="px-5 py-3 font-medium">Updater</th>
                        <th class="px-5 py-3 font-medium">Published</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($releases as $release)
                        <tr>
                            <td class="px-5 py-3 font-mono text-xs text-foreground">v{{ $release->version }}</td>
                            <td class="max-w-md truncate px-5 py-3 text-muted">{{ $release->notes ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs {{ $release->signature && $release->download_url ? 'text-positive' : 'text-subtle' }}">
                                {{ $release->signature && $release->download_url ? 'Signed' : '—' }}
                            </td>
                            <td class="px-5 py-3 text-xs text-subtle">{{ $release->published_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-4 text-sm text-subtle" colspan="4">No releases published yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $releases->links() }}
    </div>

    <details id="release-form" class="group mt-6 rounded-xl border border-border bg-surface p-5" @if ($errors->any()) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between">
            <div>
                <p class="text-sm font-medium uppercase tracking-wide text-subtle">Publish a release</p>
                <p class="mt-1 text-xs text-muted">
                    Clients calling <code class="text-foreground">GET /api/v1/version/latest</code> see this immediately after submit.
                </p>
            </div>
            <span class="text-subtle transition-transform group-open:-rotate-180">&#9662;</span>
        </summary>

        <form method="POST" action="{{ route('dashboard.releases.store') }}" class="mt-4 space-y-4">
            @csrf

            <div>
                <label for="version" class="mb-1 block text-xs font-medium text-muted">Version</label>
                <input
                    type="text" name="version" id="version" value="{{ old('version') }}"
                    placeholder="3.0.1"
                    class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
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
                    class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                >{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="signature" class="mb-1 block text-xs font-medium text-muted">Updater signature (optional)</label>
                <p class="mb-1 text-xs text-subtle">Contents of the <code class="text-foreground">.sig</code> file the Tauri bundler produces next to the installer. Leave both this and the download URL blank if this release isn't built with updater artifacts.</p>
                <textarea
                    name="signature" id="signature" rows="2"
                    placeholder="dW50cnVzdGVkIGNvbW1lbnQ6..."
                    class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 font-mono text-xs text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                >{{ old('signature') }}</textarea>
                @error('signature')
                    <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="download_url" class="mb-1 block text-xs font-medium text-muted">Installer download URL (optional)</label>
                <input
                    type="text" name="download_url" id="download_url" value="{{ old('download_url') }}"
                    placeholder="https://.../rezureapp_1.5.0_x64-setup.exe"
                    class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                >
                @error('download_url')
                    <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand/90">
                Publish
            </button>
        </form>
    </details>
</x-dashboard-layout>
