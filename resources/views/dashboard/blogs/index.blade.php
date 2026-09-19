<x-dashboard-layout title="Blog" subtitle="Manage static articles, publishing workflow, and triggers for rezure.redscale.my.id.">
    <x-slot:actions>
        <a
            href="https://rezure.redscale.my.id/blog/"
            target="_blank"
            rel="noreferrer"
            class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-sm font-medium text-muted hover:bg-surface-raised hover:text-foreground transition-colors"
        >
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
            View live
        </a>

        <a
            href="{{ route('dashboard.blogs.create') }}"
            class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90 transition-colors"
        >
            + New article
        </a>
    </x-slot:actions>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg border border-negative/30 bg-negative/10 px-4 py-2.5 text-sm text-negative">
            {{ session('error') }}
        </div>
    @endif

    {{-- Compact Stat Cards --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
        <a
            href="{{ route('dashboard.blogs.index', array_filter(['q' => $searchQuery])) }}"
            class="rounded-lg border border-border bg-surface px-4 py-2.5 transition-all hover:border-brand/40 hover:bg-surface-raised {{ $currentStatus === 'all' ? 'bg-surface-raised ring-1 ring-border' : '' }}"
        >
            <p class="text-[11px] font-medium uppercase tracking-wider text-subtle">Total articles</p>
            <p class="mt-0.5 text-lg font-semibold tracking-tight text-foreground">{{ $stats['total'] }}</p>
        </a>
        <a
            href="{{ route('dashboard.blogs.index', array_filter(['status' => 'published', 'q' => $searchQuery])) }}"
            class="rounded-lg border border-border bg-surface px-4 py-2.5 transition-all hover:border-positive/40 hover:bg-surface-raised {{ $currentStatus === 'published' ? 'border-positive/30 bg-surface-raised ring-1 ring-positive/20' : '' }}"
        >
            <p class="text-[11px] font-medium uppercase tracking-wider text-subtle">Published (Live)</p>
            <p class="mt-0.5 text-lg font-semibold tracking-tight text-positive">{{ $stats['published'] }}</p>
        </a>
        <a
            href="{{ route('dashboard.blogs.index', array_filter(['status' => 'draft', 'q' => $searchQuery])) }}"
            class="rounded-lg border border-border bg-surface px-4 py-2.5 transition-all hover:border-brand/40 hover:bg-surface-raised {{ $currentStatus === 'draft' ? 'bg-surface-raised ring-1 ring-border' : '' }}"
        >
            <p class="text-[11px] font-medium uppercase tracking-wider text-subtle">Drafts</p>
            <p class="mt-0.5 text-lg font-semibold tracking-tight text-foreground">{{ $stats['draft'] }}</p>
        </a>
        <a
            href="{{ route('dashboard.blogs.index', array_filter(['status' => 'deleted', 'q' => $searchQuery])) }}"
            class="rounded-lg border border-border bg-surface px-4 py-2.5 transition-all hover:border-negative/40 hover:bg-surface-raised {{ $currentStatus === 'deleted' ? 'border-negative/30 bg-surface-raised ring-1 ring-negative/20' : '' }}"
        >
            <p class="text-[11px] font-medium uppercase tracking-wider text-subtle">Deleted</p>
            <p class="mt-0.5 text-lg font-semibold tracking-tight text-negative">{{ $stats['deleted'] }}</p>
        </a>
    </div>

    {{-- Main Container matching tickets and releases --}}
    <div class="mt-4 rounded-xl border border-border bg-surface">
        {{-- Integrated Table Toolbar --}}
        <div class="flex flex-col gap-3 border-b border-border px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-1">
                <a
                    href="{{ route('dashboard.blogs.index', array_filter(['q' => $searchQuery])) }}"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors {{ $currentStatus === 'all' ? 'bg-surface-raised text-foreground font-semibold' : 'text-muted hover:text-foreground' }}"
                >
                    All <span class="ml-1 text-subtle">({{ $stats['total'] }})</span>
                </a>
                <a
                    href="{{ route('dashboard.blogs.index', array_filter(['status' => 'published', 'q' => $searchQuery])) }}"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors {{ $currentStatus === 'published' ? 'bg-surface-raised text-positive font-semibold' : 'text-muted hover:text-foreground' }}"
                >
                    Published <span class="ml-1 text-subtle">({{ $stats['published'] }})</span>
                </a>
                <a
                    href="{{ route('dashboard.blogs.index', array_filter(['status' => 'draft', 'q' => $searchQuery])) }}"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors {{ $currentStatus === 'draft' ? 'bg-surface-raised text-foreground font-semibold' : 'text-muted hover:text-foreground' }}"
                >
                    Drafts <span class="ml-1 text-subtle">({{ $stats['draft'] }})</span>
                </a>
                <a
                    href="{{ route('dashboard.blogs.index', array_filter(['status' => 'deleted', 'q' => $searchQuery])) }}"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors {{ $currentStatus === 'deleted' ? 'bg-surface-raised text-negative font-semibold' : 'text-muted hover:text-foreground' }}"
                >
                    Deleted <span class="ml-1 text-subtle">({{ $stats['deleted'] }})</span>
                </a>
            </div>

            <form method="GET" action="{{ route('dashboard.blogs.index') }}" class="flex items-center gap-2">
                @if ($currentStatus !== 'all')
                    <input type="hidden" name="status" value="{{ $currentStatus }}">
                @endif
                <div class="relative">
                    <input
                        type="search"
                        name="q"
                        value="{{ $searchQuery }}"
                        placeholder="Search title, tags..."
                        class="w-56 rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-xs text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                    >
                </div>
                @if ($searchQuery)
                    <a href="{{ route('dashboard.blogs.index', $currentStatus !== 'all' ? ['status' => $currentStatus] : []) }}" class="text-xs text-muted hover:text-foreground">
                        Clear
                    </a>
                @endif
            </form>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs uppercase tracking-wide text-subtle">
                        <th class="px-5 py-3 font-medium">Article</th>
                        <th class="px-5 py-3 font-medium">Status</th>
                        <th class="px-5 py-3 font-medium">Tags</th>
                        <th class="px-5 py-3 font-medium">Author</th>
                        <th class="px-5 py-3 font-medium">Published / Deleted</th>
                        <th class="px-5 py-3 font-medium">Logs</th>
                        <th class="px-5 py-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($blogs as $post)
                        @php
                            $isTrashed = $post->trashed();
                            $latestLog = $post->latestBuildLog;
                        @endphp
                        <tr>
                            {{-- Title & Slug --}}
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    @if ($isTrashed)
                                        <span class="font-medium text-muted line-through">
                                            {{ $post->title }}
                                        </span>
                                    @else
                                        <a href="{{ route('dashboard.blogs.edit', $post) }}" class="font-medium text-foreground hover:text-brand hover:underline">
                                            {{ $post->title }}
                                        </a>
                                    @endif

                                    @if ($post->status === 'published' && ! $isTrashed)
                                        <a
                                            href="https://rezure.redscale.my.id/blog/posts/{{ $post->slug }}"
                                            target="_blank"
                                            rel="noreferrer"
                                            class="text-subtle hover:text-brand transition-colors"
                                            title="View live post"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                                <polyline points="15 3 21 3 21 9"></polyline>
                                                <line x1="10" y1="14" x2="21" y2="3"></line>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                                <div class="mt-0.5 font-mono text-[11px] text-subtle">
                                    /blog/posts/{{ $post->slug }}
                                </div>
                            </td>

                            {{-- Status --}}
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if ($isTrashed)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-negative/15 px-2.5 py-0.5 text-xs font-medium text-negative">
                                        <span class="h-1.5 w-1.5 rounded-full bg-negative"></span>
                                        Deleted
                                    </span>
                                @elseif ($post->status === 'published')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-positive/15 px-2.5 py-0.5 text-xs font-medium text-positive">
                                        <span class="h-1.5 w-1.5 rounded-full bg-positive"></span>
                                        Published
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-raised px-2.5 py-0.5 text-xs font-medium text-subtle">
                                        <span class="h-1.5 w-1.5 rounded-full bg-subtle"></span>
                                        Draft
                                    </span>
                                @endif
                            </td>

                            {{-- Tags --}}
                            <td class="px-5 py-3.5">
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($post->tags ?? [] as $tag)
                                        <span class="rounded bg-surface-raised px-2 py-0.5 font-mono text-[11px] text-muted">
                                            {{ $tag }}
                                        </span>
                                    @empty
                                        <span class="text-xs text-subtle">&mdash;</span>
                                    @endforelse
                                </div>
                            </td>

                            {{-- Author --}}
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    @if (!empty($post->author['avatar']))
                                        <img src="{{ $post->author['avatar'] }}" alt="{{ $post->author['name'] ?? 'Author' }}" class="h-5 w-5 rounded-full object-cover">
                                    @endif
                                    <span class="text-xs text-foreground">{{ $post->author['name'] ?? 'Team' }}</span>
                                </div>
                            </td>

                            {{-- Published / Deleted Date --}}
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                @if ($isTrashed)
                                    <div class="text-xs text-negative">Deleted: {{ $post->deleted_at?->format('M d, Y') ?? '&mdash;' }}</div>
                                    @if ($post->deleted_at)
                                        <div class="text-[11px] text-subtle">{{ $post->deleted_at->diffForHumans() }}</div>
                                    @endif
                                @else
                                    <div class="text-xs text-foreground">{{ $post->published_at?->format('M d, Y') ?? '&mdash;' }}</div>
                                    @if ($post->published_at)
                                        <div class="text-[11px] text-subtle">{{ $post->published_at->diffForHumans() }}</div>
                                    @endif
                                @endif
                            </td>

                            {{-- Logs Column --}}
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <a
                                        href="{{ route('dashboard.blogs.logs', $post) }}"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-raised px-2.5 py-1 text-xs font-medium text-foreground hover:border-brand hover:text-brand transition-colors"
                                        title="View build & deploy logs"
                                    >
                                        <svg class="h-3.5 w-3.5 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                            <polyline points="14 2 14 8 20 8"></polyline>
                                            <line x1="16" y1="13" x2="8" y2="13"></line>
                                            <line x1="16" y1="17" x2="8" y2="17"></line>
                                            <polyline points="10 9 9 9 8 9"></polyline>
                                        </svg>
                                        View
                                    </a>

                                    @if ($latestLog && $latestLog->status === 'failed')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-negative/15 px-2 py-0.5 text-[10px] font-semibold text-negative" title="{{ $latestLog->error_message }}">
                                            <span class="h-1 w-1 rounded-full bg-negative"></span>
                                            Error
                                        </span>
                                    @elseif ($latestLog && in_array($latestLog->status, ['dispatched', 'in_progress']))
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-semibold text-amber-500">
                                            <span class="h-1 w-1 rounded-full bg-amber-500 animate-pulse"></span>
                                            Building
                                        </span>
                                    @endif
                                </div>
                            </td>

                            {{-- Actions --}}
                            <td class="px-5 py-3.5 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-3">
                                    @if ($isTrashed)
                                        <form method="POST" action="{{ route('dashboard.blogs.restore', $post) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                type="submit"
                                                class="text-xs font-medium text-positive hover:underline"
                                            >
                                                Restore
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('dashboard.blogs.force-delete', $post) }}" onsubmit="return confirm('Hapus artikel ini secara permanen? Data tidak dapat dikembalikan.');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-negative hover:underline">
                                                Delete Permanently
                                            </button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('dashboard.blogs.toggle-publish', $post) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button
                                                type="submit"
                                                class="text-xs font-medium {{ $post->status === 'published' ? 'text-subtle hover:text-foreground' : 'text-positive hover:underline' }}"
                                            >
                                                {{ $post->status === 'published' ? 'Unpublish' : 'Publish' }}
                                            </button>
                                        </form>

                                        <a href="{{ route('dashboard.blogs.edit', $post) }}" class="text-xs font-medium text-brand hover:underline">
                                            Edit
                                        </a>

                                        <form method="POST" action="{{ route('dashboard.blogs.destroy', $post) }}" onsubmit="return confirm('Delete this article? Static site rebuild will be triggered.');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-medium text-negative hover:underline">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-8 text-center text-sm text-subtle" colspan="7">
                                @if ($searchQuery)
                                    No articles matching "{{ $searchQuery }}".
                                @elseif ($currentStatus === 'deleted')
                                    No deleted articles.
                                @else
                                    No articles found. <a href="{{ route('dashboard.blogs.create') }}" class="text-brand hover:underline">Create one</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($blogs->hasPages())
            <div class="border-t border-border px-5 py-3">
                {{ $blogs->links() }}
            </div>
        @endif
    </div>
</x-dashboard-layout>