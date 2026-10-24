@props([
    'rows',
    'dimension',
    'filters',
    'columns' => ['visitors', 'sessions', 'bounce_rate'],
    'label' => null,
    'drilldown' => true,
])

@php
    use App\Support\WebDisplay;
    use Illuminate\Support\Arr;

    $headings = [
        'visitors' => 'Visitors',
        'sessions' => 'Sessions',
        'pageviews' => 'Views',
        'bounce_rate' => 'Bounce',
        'avg_duration' => 'Duration',
        'conversions' => 'Downloads',
        'conversion_rate' => 'Conv.',
        'share' => 'Share',
    ];

    $format = fn (string $column, mixed $value): string => match ($column) {
        'bounce_rate', 'conversion_rate', 'share' => WebDisplay::percent($value),
        'avg_duration' => WebDisplay::duration($value),
        default => number_format((int) $value),
    };
@endphp

@if (count($rows) === 0)
    <p class="py-6 text-center text-xs text-subtle">No data for this period.</p>
@else
    <table class="w-full table-fixed text-sm">
        <thead>
            <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                <th class="pb-2 font-medium">{{ $label ?? \App\Services\WebAnalytics\WebDimensions::label($dimension) }}</th>
                @foreach ($columns as $column)
                    <th class="w-20 pb-2 text-right font-medium">{{ $headings[$column] ?? $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr class="border-t border-border">
                    <td class="relative py-2 pr-3">
                        <span class="absolute inset-y-1 left-0 rounded bg-brand/10" style="width: {{ min(100, $row['share'] ?? 0) }}%"></span>
                        @if ($drilldown && ! $filters->filter($dimension))
                            <a
                                href="{{ request()->url() }}?{{ Arr::query($filters->queryWith($dimension, $row['value'])) }}"
                                class="relative block truncate hover:text-brand"
                                title="Filter by {{ WebDisplay::value($dimension, $row['value']) }}"
                            >{{ WebDisplay::value($dimension, $row['value']) }}</a>
                        @else
                            <span class="relative block truncate">{{ WebDisplay::value($dimension, $row['value']) }}</span>
                        @endif
                    </td>
                    @foreach ($columns as $column)
                        <td class="py-2 text-right tabular-nums text-muted">{{ $format($column, $row[$column] ?? 0) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
