@php
    use App\Support\WebDisplay;
@endphp

<x-website.layout title="Audience" subtitle="Where visitors are, which language they read, and whether they come back." :filters="$filters">
    <div class="grid gap-4 lg:grid-cols-5">
        <x-website.card title="Visitors by country" class="lg:col-span-3">
            <div class="h-80">
                <canvas data-geo-chart="{{ json_encode(['values' => $report['map'], 'metric' => 'Visitors']) }}" aria-label="World map of visitors by country"></canvas>
            </div>
        </x-website.card>

        <x-website.card title="Countries" class="lg:col-span-2">
            <x-slot:actions>
                <a href="{{ route('dashboard.website.export', [...$filters->query(), 'report' => 'breakdown', 'dimension' => 'country']) }}" class="text-xs text-brand hover:underline">CSV</a>
            </x-slot:actions>
            <div class="max-h-80 overflow-y-auto">
                <x-website.breakdown :rows="$report['countries']" dimension="country" :filters="$filters" :columns="['visitors', 'sessions', 'share']" />
            </div>
        </x-website.card>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-5">
        <x-website.card title="Cities" subtitle="Bubble size = visitors." class="lg:col-span-3">
            <div class="h-80">
                <canvas data-bubble-map="{{ json_encode(['points' => $report['cityPoints'], 'metric' => 'Visitors']) }}" aria-label="Map of visitor cities"></canvas>
            </div>
        </x-website.card>

        <div class="grid gap-4 lg:col-span-2">
            <x-website.card title="Regions" subtitle="Filter by a country to see only its regions.">
                <x-website.breakdown :rows="$report['regions']" dimension="region" :filters="$filters" :columns="['visitors', 'sessions']" />
            </x-website.card>
            <x-website.card title="Cities">
                <x-website.breakdown :rows="$report['cities']" dimension="city" :filters="$filters" :columns="['visitors', 'sessions']" />
            </x-website.card>
        </div>
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-3">
        <x-website.card title="New vs returning visitors" class="lg:col-span-2">
            <div class="h-64">
                <canvas
                    data-stacked-bar-chart
                    data-labels="{{ json_encode($report['newVsReturning']['labels']) }}"
                    data-new-values="{{ json_encode($report['newVsReturning']['new']) }}"
                    data-returning-values="{{ json_encode($report['newVsReturning']['returning']) }}"
                ></canvas>
            </div>
        </x-website.card>

        <x-website.card title="Visitor type">
            <x-website.breakdown :rows="$report['visitorTypes']" dimension="visitor_type" :filters="$filters" :columns="['visitors', 'sessions', 'conversion_rate']" />
        </x-website.card>

        <x-website.card title="Visit number" subtitle="Sessions by how many visits the visitor had made by then.">
            <x-website.histogram :buckets="$report['frequency']" height="h-48" />
        </x-website.card>

        <x-website.card title="Time since previous visit" subtitle="Returning sessions only.">
            <x-website.histogram :buckets="$report['recency']" height="h-48" />
        </x-website.card>

        <x-website.card title="Languages">
            <x-website.breakdown :rows="$report['languages']" dimension="language" :filters="$filters" :columns="['visitors', 'share']" />
        </x-website.card>
    </div>

    <x-website.card title="Weekly retention" subtitle="Of the visitors first seen in a week, the share who came back in each following week. Always the last 8 weeks." class="mt-4">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                        <th class="pb-2 font-medium">First week</th>
                        <th class="pb-2 text-right font-medium">Visitors</th>
                        @for ($week = 1; $week < 8; $week++)
                            <th class="pb-2 text-center font-medium">W+{{ $week }}</th>
                        @endfor
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['cohorts'] as $cohort)
                        <tr class="border-t border-border">
                            <td class="py-1.5 pr-3">{{ $cohort['week'] }}</td>
                            <td class="py-1.5 pr-3 text-right tabular-nums text-muted">{{ number_format($cohort['visitors']) }}</td>
                            @for ($week = 0; $week < 7; $week++)
                                <td class="p-0.5">
                                    @if (array_key_exists($week, $cohort['weeks']))
                                        @php $value = $cohort['weeks'][$week]; @endphp
                                        <div class="heatmap-cell rounded px-1.5 py-1 text-center text-xs tabular-nums" style="--intensity: {{ $value === null ? 0 : min(1, $value / 50) }}">
                                            {{ $value === null ? '–' : WebDisplay::percent($value, 0) }}
                                        </div>
                                    @endif
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-website.card>

    <x-website.card title="Browser timezones" class="mt-4">
        <x-website.breakdown :rows="$report['timezones']" dimension="timezone" :filters="$filters" :columns="['visitors', 'sessions', 'share']" />
    </x-website.card>
</x-website.layout>
