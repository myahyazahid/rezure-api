@props(['period', 'route'])

<div class="flex items-center gap-1 rounded-lg border border-border bg-surface p-1">
    @foreach ([7 => '7d', 30 => '30d', 90 => '90d'] as $value => $label)
        <a
            href="{{ route($route, ['period' => $value]) }}"
            class="rounded-md px-3 py-1.5 text-xs font-medium transition-colors {{ $period === $value ? 'bg-brand text-white' : 'text-muted hover:text-white' }}"
        >
            {{ $label }}
        </a>
    @endforeach
</div>
