<x-website.layout title="Behavior" subtitle="How visitors move through the site and how much they engage." :filters="$filters">
    <div class="grid gap-4 lg:grid-cols-2">
        <x-website.card title="Engaged time per visit" subtitle="Time with the tab visible and in use.">
            <x-website.histogram :buckets="$report['engagedTime']" />
        </x-website.card>
        <x-website.card title="Visit duration">
            <x-website.histogram :buckets="$report['duration']" />
        </x-website.card>
        <x-website.card title="Pages per visit">
            <x-website.histogram :buckets="$report['depth']" />
        </x-website.card>
        <x-website.card title="Scroll depth" subtitle="Per pageview: how far down the page it got.">
            <x-website.histogram :buckets="$report['scroll']" />
        </x-website.card>
    </div>

    <x-website.card title="User flow" subtitle="Entry page → 2nd page → 3rd page, for the latest 5,000 visits. Visits that stop early flow into (exit)." class="mt-4">
        @if (count($report['flow']) === 0)
            <p class="py-6 text-center text-xs text-subtle">No visits for this period.</p>
        @else
            <div class="h-[28rem]">
                <canvas data-sankey-chart="{{ json_encode(['flows' => $report['flow']]) }}" aria-label="User flow diagram"></canvas>
            </div>
        @endif
    </x-website.card>

    <x-website.card title="When visitors come" :subtitle="'Visits by weekday and hour ('.now(config('app.display_timezone'))->format('T').').'" class="mt-4">
        <div class="overflow-x-auto">
            <div class="min-w-[640px] space-y-1">
                @foreach ($report['heatmap']['rows'] as $row)
                    <div class="grid grid-cols-[3rem_repeat(24,minmax(0,1fr))] items-center gap-1">
                        <span class="text-xs text-subtle">{{ $row['day'] }}</span>
                        @foreach ($row['cells'] as $cell)
                            <div class="heatmap-cell aspect-square rounded" style="--intensity: {{ $cell['intensity'] }}" title="{{ $row['day'] }} {{ sprintf('%02d:00', $cell['hour']) }} — {{ number_format($cell['total']) }} visits"></div>
                        @endforeach
                    </div>
                @endforeach
                <div class="grid grid-cols-[3rem_repeat(24,minmax(0,1fr))] gap-1">
                    <span></span>
                    @for ($hour = 0; $hour < 24; $hour++)
                        <span class="text-center text-[9px] text-subtle">{{ $hour }}</span>
                    @endfor
                </div>
            </div>
        </div>
    </x-website.card>

    <x-website.card title="Rage clicks" subtitle="Three or more fast clicks on the same spot — usually something that looks clickable but isn't, or is broken." class="mt-4">
        @forelse ($report['rageClicks'] as $rage)
            <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                <div class="min-w-0">
                    <a href="{{ route('dashboard.website.heatmap', [...$filters->query(), 'path' => $rage['path']]) }}" class="block truncate hover:text-brand">{{ $rage['path'] }}</a>
                    <span class="block truncate font-mono text-xs text-subtle">{{ $rage['selector'] }} @if ($rage['text'])“{{ $rage['text'] }}”@endif</span>
                </div>
                <span class="shrink-0 tabular-nums text-muted">{{ number_format($rage['clicks']) }} in {{ number_format($rage['sessions']) }} visits</span>
            </div>
        @empty
            <p class="py-6 text-center text-xs text-subtle">No rage clicks in this period.</p>
        @endforelse
    </x-website.card>
</x-website.layout>
