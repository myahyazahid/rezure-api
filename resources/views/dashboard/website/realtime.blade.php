<x-website.layout title="Realtime" subtitle="Who is on the website right now — refreshes every 10 seconds.">
    <div data-realtime="{{ route('dashboard.website.realtime.data') }}" data-realtime-initial="{{ json_encode($snapshot) }}">
        <div class="grid gap-4 lg:grid-cols-3">
            <x-website.card>
                <p class="text-[11px] font-medium uppercase tracking-wide text-subtle">Visitors online</p>
                <p class="mt-2 flex items-center gap-3 text-5xl font-semibold tracking-tight tabular-nums">
                    <span class="relative flex h-3 w-3">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-positive opacity-60"></span>
                        <span class="relative inline-flex h-3 w-3 rounded-full bg-positive"></span>
                    </span>
                    <span data-realtime-count>{{ number_format($snapshot['active_visitors']) }}</span>
                </p>
                <p class="mt-2 text-xs text-muted">Active in the last 5 minutes · <span data-realtime-pageviews>{{ number_format($snapshot['pageviews_30m']) }}</span> pageviews in the last 30 minutes</p>
            </x-website.card>

            <x-website.card title="Pageviews per minute" subtitle="Last 30 minutes" class="lg:col-span-2">
                <div class="h-40"><canvas data-realtime-minutes></canvas></div>
            </x-website.card>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            @foreach ([['Active pages', 'data-realtime-pages', 'Page'], ['Sources', 'data-realtime-sources', 'Source'], ['Countries', 'data-realtime-countries', 'Country']] as [$title, $attribute, $heading])
                <x-website.card :title="$title">
                    <table class="w-full table-fixed text-sm">
                        <thead>
                            <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                                <th class="pb-2 font-medium">{{ $heading }}</th>
                                <th class="w-20 pb-2 text-right font-medium">Visitors</th>
                            </tr>
                        </thead>
                        <tbody {{ $attribute }}></tbody>
                    </table>
                </x-website.card>
            @endforeach
        </div>

        <x-website.card title="Latest pageviews" class="mt-4">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                            <th class="pb-2 font-medium">Page</th>
                            <th class="pb-2 text-right font-medium">Visitor</th>
                            <th class="pb-2 text-right font-medium">Source</th>
                            <th class="pb-2 text-right font-medium">When</th>
                        </tr>
                    </thead>
                    <tbody data-realtime-feed></tbody>
                </table>
            </div>
        </x-website.card>
    </div>
</x-website.layout>
