@php
    use App\Support\WebDisplay;

    $first = max(1, $report['stages'][0]['count']);
@endphp

<x-website.layout title="Website → app" subtitle="From a website visit to a download, an install, and a user still active a week later." :filters="$filters">
    <x-website.card title="Funnel" subtitle="Each stage is counted on its own over the period (aggregate, not the same people); installs also come straight from GitHub, so they can exceed website downloads.">
        <div class="space-y-5">
            @foreach ($report['stages'] as $index => $stage)
                @php $share = round($stage['count'] / $first * 100, 1); @endphp
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium"><span class="mr-2 text-subtle">{{ $index + 1 }}</span>{{ $stage['label'] }}</span>
                        <span class="tabular-nums text-muted">{{ number_format($stage['count']) }} · {{ WebDisplay::percent($share) }}</span>
                    </div>
                    <div class="mt-1.5 h-3 w-full overflow-hidden rounded-full bg-surface-raised">
                        <div class="h-full rounded-full bg-brand" style="width: {{ min(100, $share) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="mt-5 text-xs text-subtle">
            {{ number_format($report['downloads']) }} download clicks in total (including people without the tracker).
            "Active after 7 days" only counts the {{ number_format($report['eligibleDevices']) }} installs at least a week old.
        </p>
    </x-website.card>

    <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-website.kpi label="Installs matched to a visitor" :value="number_format($report['attributed'])" hint="Same IP as a website download within the attribution window — probabilistic" />
        <x-website.kpi label="…still active after 7 days" :value="number_format($report['attributedActive'])" />
        <x-website.kpi label="GitHub downloads (period)" :value="$report['github']['total'] === null ? 'Not synced' : number_format($report['github']['total'])" hint="From the daily snapshot of GitHub release download counts" />
        <x-website.kpi label="Website share of downloads" :value="$report['github']['total'] ? WebDisplay::percent($report['downloads'] / max(1, $report['github']['total']) * 100) : '–'" />
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <x-website.card title="Installs by first channel" subtitle="Website channel of visitors matched to an install.">
            @forelse ($report['attributedChannels'] as $row)
                <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                    <span class="truncate">{{ WebDisplay::value('channel', $row['channel']) }}</span>
                    <span class="tabular-nums text-muted">{{ number_format($row['installs']) }} installs · {{ number_format($row['active']) }} active</span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No installs matched to visitors yet.</p>
            @endforelse
        </x-website.card>

        <x-website.card title="Website downloads by version">
            @forelse ($report['byVersion'] as $row)
                <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                    <span>{{ $row['version'] === \App\Services\WebAnalytics\WebAnalyticsQuery::NONE ? 'Unknown' : 'v'.$row['version'] }}</span>
                    <span class="tabular-nums text-muted">{{ number_format($row['total']) }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No downloads in this period.</p>
            @endforelse
        </x-website.card>

        <x-website.card title="GitHub downloads by version" subtitle="In the period / all time.">
            @forelse ($report['github']['versions'] as $row)
                <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                    <span>v{{ $row['version'] }}</span>
                    <span class="tabular-nums text-muted">{{ number_format($row['downloads']) }} / {{ number_format($row['all_time']) }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">Runs nightly (app:sync-github-download-counts).</p>
            @endforelse
        </x-website.card>
    </div>

    <x-website.card title="Downloads by channel" class="mt-4">
        <x-website.breakdown :rows="$report['byChannel']" dimension="channel" :filters="$filters" :columns="['visitors', 'sessions', 'conversions', 'conversion_rate']" />
    </x-website.card>
</x-website.layout>
