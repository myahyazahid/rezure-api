<x-dashboard-layout title="Stickers" subtitle="What the client's Decorations → Browse page offers — backs GET /api/v1/stickers.">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-xl border border-border bg-surface">
        <div class="flex items-center justify-between px-5 py-4">
            <div>
                <p class="text-sm font-medium uppercase tracking-wide text-subtle">Catalog</p>
                <p class="mt-0.5 text-xs text-muted">
                    {{ $stickers->where('is_published', true)->count() }} published · {{ $stickers->where('is_published', false)->count() }} hidden.
                    PNG, WebP or SVG, up to {{ intdiv(\App\Models\Sticker::MAX_BYTES, 1024) }} KB.
                </p>
            </div>
            <a href="{{ route('dashboard.stickers.create') }}" class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90">
                + Add sticker
            </a>
        </div>

        <div class="overflow-x-auto border-t border-border">
            <table class="w-full text-left text-sm">
                <tbody class="divide-y divide-border">
                    @forelse ($stickers as $sticker)
                        <tr>
                            <td class="w-16 px-5 py-3">
                                <img
                                    src="{{ route('dashboard.stickers.preview', $sticker) }}"
                                    alt=""
                                    class="h-10 w-10 rounded-lg bg-surface-raised object-contain p-1"
                                    loading="lazy"
                                >
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-foreground">{{ $sticker->name }}</p>
                                <p class="font-mono text-xs text-subtle">{{ $sticker->slug }}</p>
                            </td>
                            <td class="px-5 py-3 text-muted">{{ $sticker->category }}</td>
                            <td class="px-5 py-3 text-xs text-muted">{{ strtoupper($sticker->format) }} · {{ number_format($sticker->size / 1024, 1) }} KB</td>
                            <td class="px-5 py-3">
                                <span class="text-xs font-medium {{ $sticker->is_published ? 'text-positive' : 'text-subtle' }}">
                                    {{ $sticker->is_published ? 'Published' : 'Hidden' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right">
                                <form method="POST" action="{{ route('dashboard.stickers.toggle-publish', $sticker) }}" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="text-xs font-medium text-muted hover:text-foreground hover:underline">
                                        {{ $sticker->is_published ? 'Hide' : 'Publish' }}
                                    </button>
                                </form>
                                <a href="{{ route('dashboard.stickers.edit', $sticker) }}" class="ml-3 text-xs font-medium text-brand hover:underline">Edit</a>
                                <form method="POST" action="{{ route('dashboard.stickers.destroy', $sticker) }}" class="ml-3 inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-negative hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-5 py-4 text-sm text-subtle" colspan="6">No stickers yet — add one and it appears in every client's Browse page.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-dashboard-layout>
