@props(['title' => null, 'subtitle' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-surface p-5']) }}>
    @if ($title || isset($actions))
        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                @if ($title)
                    <p class="text-sm font-medium">{{ $title }}</p>
                @endif
                @if ($subtitle)
                    <p class="mt-0.5 text-xs text-muted">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</div>
