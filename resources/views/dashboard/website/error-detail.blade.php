@php
    $sample = $detail['sample'];
    $tz = config('app.display_timezone');
@endphp

<x-website.layout title="Website error" :subtitle="number_format($detail['occurrences']).' occurrences in the period'" :filters="$filters">
    <x-website.card>
        <p class="font-medium text-negative">{{ $sample->message }}</p>
        <p class="mt-1 text-xs text-subtle">{{ $sample->source ?? 'unknown source' }}{{ $sample->line !== null ? ':'.$sample->line.':'.$sample->column : '' }}</p>
        @if ($sample->stack)
            <pre class="mt-4 max-h-80 overflow-auto rounded-lg bg-surface-raised p-3 font-mono text-xs leading-relaxed text-muted">{{ $sample->stack }}</pre>
        @endif
    </x-website.card>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        @foreach (['browsers' => 'Browsers', 'pages' => 'Pages'] as $key => $title)
            <x-website.card :title="$title">
                @foreach ($detail[$key] as $row)
                    <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                        <span class="truncate">{{ $row['label'] }}</span>
                        <span class="tabular-nums text-muted">{{ number_format($row['total']) }}</span>
                    </div>
                @endforeach
            </x-website.card>
        @endforeach

        <x-website.card title="Latest occurrences">
            @foreach ($detail['recent'] as $error)
                <a href="{{ $error->session ? route('dashboard.website.sessions.show', $error->session) : '#' }}" class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0 hover:text-brand">
                    <span class="truncate">{{ \App\Support\WebDisplay::flag($error->session?->country_code) }} {{ $error->browser }} {{ $error->browser_version }} · {{ $error->os }}</span>
                    <span class="shrink-0 text-xs text-subtle">{{ $error->occurred_at->setTimezone($tz)->format('d M H:i') }}</span>
                </a>
            @endforeach
        </x-website.card>
    </div>
</x-website.layout>
