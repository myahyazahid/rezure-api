@props(['title', 'subtitle' => null, 'filters' => null])

@php
    use App\Services\WebAnalytics\WebAnalyticsFilters;
    use App\Services\WebAnalytics\WebDimensions;
    use App\Support\WebDisplay;

    $query = $filters?->query() ?? [];

    // The website section's own navigation — kept out of the main sidebar,
    // which only gets one "Website" entry.
    $tabs = [
        ['route' => 'dashboard.website.overview', 'label' => 'Overview'],
        ['route' => 'dashboard.website.realtime', 'label' => 'Realtime', 'plain' => true],
        ['route' => 'dashboard.website.pages', 'label' => 'Pages'],
        ['route' => 'dashboard.website.content', 'label' => 'Content'],
        ['route' => 'dashboard.website.acquisition', 'label' => 'Acquisition'],
        ['route' => 'dashboard.website.audience', 'label' => 'Audience'],
        ['route' => 'dashboard.website.visitors', 'label' => 'Visitors'],
        ['route' => 'dashboard.website.technology', 'label' => 'Technology'],
        ['route' => 'dashboard.website.behavior', 'label' => 'Behavior'],
        ['route' => 'dashboard.website.heatmap', 'label' => 'Heatmap'],
        ['route' => 'dashboard.website.events', 'label' => 'Events'],
        ['route' => 'dashboard.website.goals', 'label' => 'Goals'],
        ['route' => 'dashboard.website.funnels', 'label' => 'Funnels'],
        ['route' => 'dashboard.website.performance', 'label' => 'Performance'],
        ['route' => 'dashboard.website.errors', 'label' => 'Errors'],
        ['route' => 'dashboard.website.conversion', 'label' => 'Conversion'],
    ];
@endphp

<x-dashboard-layout :title="$title" :subtitle="$subtitle">
    @isset($actions)
        <x-slot:actions>{{ $actions }}</x-slot:actions>
    @endisset

    <nav class="-mx-4 mb-5 overflow-x-auto border-b border-border px-4 sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8" aria-label="Website analytics">
        <div class="flex min-w-max gap-1 pb-2">
            @foreach ($tabs as $tab)
                @php
                    $active = request()->routeIs($tab['route']) || request()->routeIs($tab['route'].'.*');
                @endphp
                <a
                    href="{{ route($tab['route'], ($tab['plain'] ?? false) ? [] : $query) }}"
                    @class([
                        'rounded-md px-3 py-1.5 text-xs font-medium transition-colors',
                        'bg-brand text-white' => $active,
                        'text-muted hover:bg-surface-raised hover:text-foreground' => ! $active,
                    ])
                >{{ $tab['label'] }}</a>
            @endforeach
        </div>
    </nav>

    @if ($filters)
        <form method="GET" action="{{ request()->url() }}" class="mb-5 flex flex-wrap items-center gap-2">
            @foreach ($filters->dimensions as $key => $value)
                <input type="hidden" name="f[{{ $key }}]" value="{{ $value }}">
            @endforeach

            <select name="period" data-period-select aria-label="Period" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-foreground focus:border-brand focus:outline-none">
                @foreach (WebAnalyticsFilters::PERIODS as $value => $label)
                    <option value="{{ $value }}" @selected($filters->period === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <div data-custom-range class="flex items-center gap-2" @if ($filters->period !== 'custom') hidden @endif>
                <x-dashboard.date-picker name="from" :value="$filters->from->toDateString()" label="From" />
                <span class="text-xs text-subtle">to</span>
                <x-dashboard.date-picker name="to" :value="$filters->to->toDateString()" label="To" />
            </div>

            <select name="compare" data-autosubmit aria-label="Compare to" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-foreground focus:border-brand focus:outline-none">
                @foreach (WebAnalyticsFilters::COMPARISONS as $value => $label)
                    <option value="{{ $value }}" @selected($filters->compare === $value)>vs {{ strtolower($label) }}</option>
                @endforeach
            </select>

            <select name="interval" data-autosubmit aria-label="Chart interval" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-foreground focus:border-brand focus:outline-none">
                <option value="" @selected(request('interval') === null)>Auto interval ({{ $filters->granularity() }})</option>
                @foreach (WebAnalyticsFilters::GRANULARITIES as $granularity)
                    <option value="{{ $granularity }}" @selected(request('interval') === $granularity)>By {{ $granularity }}</option>
                @endforeach
            </select>

            <span class="ml-auto text-xs text-subtle">
                {{ $filters->from->format('d M Y') }} – {{ $filters->to->format('d M Y') }} · {{ now(config('app.display_timezone'))->format('T') }}
            </span>
        </form>

        @if ($filters->hasFilters())
            <div class="-mt-2 mb-5 flex flex-wrap items-center gap-2">
                <span class="text-xs text-subtle">Filtered by</span>
                @foreach ($filters->dimensions as $key => $value)
                    <a
                        href="{{ request()->url() }}?{{ \Illuminate\Support\Arr::query($filters->queryWithout($key)) }}"
                        class="group inline-flex items-center gap-1.5 rounded-full border border-brand/40 bg-brand/10 px-2.5 py-1 text-xs text-foreground hover:border-brand"
                        title="Remove filter"
                    >
                        <span class="text-muted">{{ WebDimensions::label($key) }}:</span>
                        <span class="max-w-56 truncate font-medium">{{ WebDisplay::filterValue($key, $value) }}</span>
                        <span class="text-subtle group-hover:text-brand">×</span>
                    </a>
                @endforeach
                <a href="{{ request()->url() }}?{{ \Illuminate\Support\Arr::query(array_diff_key($filters->query(), ['f' => true])) }}" class="text-xs text-brand hover:underline">Clear all</a>
            </div>
        @endif
    @endif

    {{ $slot }}
</x-dashboard-layout>
