<x-dashboard-layout
    title="{{ $sticker ? 'Edit sticker' : 'Add sticker' }}"
    subtitle="Shown on the client's Decorations → Browse page — backs GET /api/v1/stickers."
>
    <a href="{{ route('dashboard.stickers.index') }}" class="mb-4 inline-block text-sm text-muted hover:text-foreground">&larr; Back to Stickers</a>

    <form
        method="POST"
        action="{{ $sticker ? route('dashboard.stickers.update', $sticker) : route('dashboard.stickers.store') }}"
        enctype="multipart/form-data"
        class="max-w-lg space-y-4 rounded-xl border border-border bg-surface p-5"
    >
        @csrf
        @if ($sticker)
            @method('PUT')
        @endif

        <div>
            <label for="name" class="mb-1 block text-xs font-medium text-muted">Name</label>
            <input
                type="text" name="name" id="name" value="{{ old('name', $sticker->name ?? '') }}"
                placeholder="Pink bow"
                class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
            >
            @error('name')
                <p class="mt-1 text-xs text-negative">{{ $message }}</p>
            @enderror
            @if ($sticker)
                <p class="mt-1 text-xs text-subtle">
                    Its id stays <span class="font-mono">{{ $sticker->slug }}</span> — installed clients saved it under that.
                </p>
            @endif
        </div>

        <div>
            <label for="category" class="mb-1 block text-xs font-medium text-muted">Category</label>
            <input
                type="text" name="category" id="category" list="sticker-categories"
                value="{{ old('category', $sticker->category ?? 'general') }}"
                class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
            >
            <datalist id="sticker-categories">
                @foreach ($categories as $category)
                    <option value="{{ $category }}"></option>
                @endforeach
            </datalist>
            @error('category')
                <p class="mt-1 text-xs text-negative">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-subtle">Pick one or type a new one — it becomes a filter on the client's Browse page.</p>
        </div>

        <div>
            <label for="file" class="mb-1 block text-xs font-medium text-muted">
                Image {{ $sticker ? '(leave empty to keep the current one)' : '' }}
            </label>

            @if ($sticker)
                <img
                    src="{{ route('dashboard.stickers.preview', $sticker) }}" alt=""
                    class="mb-2 h-20 w-20 rounded-lg bg-surface-raised object-contain p-2"
                >
            @endif

            <input
                type="file" name="file" id="file" accept=".svg,.png,.webp,image/svg+xml,image/png,image/webp"
                class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-raised file:px-3 file:py-2 file:text-sm file:text-foreground"
            >
            @error('file')
                <p class="mt-1 text-xs text-negative">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-subtle">
                PNG, WebP or SVG, up to {{ intdiv(\App\Models\Sticker::MAX_BYTES, 1024) }} KB. A transparent background
                works best. An SVG can't contain scripts, event handlers or a DOCTYPE.
            </p>
        </div>

        <label class="flex items-center gap-2 text-sm text-foreground">
            <input type="hidden" name="is_published" value="0">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $sticker->is_published ?? true))>
            Published
            <span class="text-xs text-subtle">— visible on the client's Browse page</span>
        </label>

        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand/90">
            {{ $sticker ? 'Save changes' : 'Add sticker' }}
        </button>
    </form>
</x-dashboard-layout>
