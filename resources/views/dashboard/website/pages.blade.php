<x-website.layout title="Pages" subtitle="Every page of the website — how it's reached, read, and left." :filters="$filters">
    <x-slot:actions>
        <a href="{{ route('dashboard.website.export', [...$filters->query(), 'report' => 'pages']) }}" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-muted hover:text-foreground">Export CSV</a>
    </x-slot:actions>

    <x-website.card>
        <x-slot:actions>
            <form method="GET" action="{{ route('dashboard.website.pages') }}" class="flex items-center gap-2">
                @include('dashboard.website.partials.filter-inputs')
                <input type="hidden" name="tab" value="{{ $tab }}">
                <input type="search" name="q" value="{{ $search }}" placeholder="Search paths…" class="w-48 rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-sm placeholder:text-subtle focus:border-brand focus:outline-none">
            </form>
        </x-slot:actions>

        <div class="mb-4 flex gap-1">
            @foreach ($tabs as $key => $label)
                <a
                    href="{{ route('dashboard.website.pages', [...$filters->query(), 'tab' => $key, 'q' => $search]) }}"
                    @class([
                        'rounded-md px-3 py-1.5 text-xs font-medium',
                        'bg-surface-raised text-foreground' => $tab === $key,
                        'text-muted hover:text-foreground' => $tab !== $key,
                    ])
                >{{ $label }}</a>
            @endforeach
        </div>

        @include('dashboard.website.partials.pages-table', ['pages' => $pages, 'entries' => false])
    </x-website.card>
</x-website.layout>
