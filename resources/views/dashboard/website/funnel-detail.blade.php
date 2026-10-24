@php
    use App\Support\WebDisplay;
@endphp

<x-website.layout :title="$funnel->name" :subtitle="'Counted per '.$funnel->scope.($funnel->scope === 'visitor' ? ', within '.$funnel->window_hours.' hours of the first step' : '').'.'" :filters="$filters">
    <x-slot:actions>
        <form method="POST" action="{{ route('dashboard.website.funnels.destroy', $funnel) }}">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-muted hover:border-negative/40 hover:text-negative">Delete funnel</button>
        </form>
    </x-slot:actions>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
        <x-website.kpi label="Entered" :value="number_format($result['entered'])" />
        <x-website.kpi label="Completed" :value="number_format($result['completed'])" />
        <x-website.kpi label="Conversion" :value="WebDisplay::percent($result['entered'] > 0 ? $result['completed'] / $result['entered'] * 100 : 0)" />
    </div>

    <x-website.card title="Steps" class="mt-4">
        <div class="space-y-5">
            @foreach ($result['steps'] as $index => $step)
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="font-medium"><span class="mr-2 text-subtle">{{ $index + 1 }}</span>{{ $step['label'] }}</span>
                        <span class="text-muted">
                            {{ number_format($step['count']) }} · {{ WebDisplay::percent($step['conversion']) }}
                            @if ($index > 0)
                                <span @class(['ml-2', 'text-negative' => $step['drop_off'] >= 50])>−{{ WebDisplay::percent($step['drop_off']) }} dropped</span>
                                @if ($step['median_seconds'] !== null)
                                    <span class="ml-2 text-subtle">median {{ WebDisplay::duration($step['median_seconds']) }} after previous step</span>
                                @endif
                            @endif
                        </span>
                    </div>
                    <div class="mt-1.5 h-3 w-full overflow-hidden rounded-full bg-surface-raised">
                        <div class="h-full rounded-full bg-brand" style="width: {{ $step['conversion'] }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-website.card>

    @if ($funnel->scope === 'session')
        <x-website.card :title="'Completion by '.strtolower($breakdowns[$by])" class="mt-4">
            <x-slot:actions>
                @foreach ($breakdowns as $key => $label)
                    <a href="{{ route('dashboard.website.funnels.show', [$funnel, ...$filters->query(), 'by' => $key]) }}" @class([
                        'rounded-md px-2.5 py-1 text-xs',
                        'bg-surface-raised text-foreground' => $by === $key,
                        'text-muted hover:text-foreground' => $by !== $key,
                    ])>{{ $label }}</a>
                @endforeach
            </x-slot:actions>

            @forelse ($result['breakdown'] as $row)
                <div class="relative flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                    <span class="absolute inset-y-1 left-0 rounded bg-brand/10" style="width: {{ $row['rate'] }}%"></span>
                    <span class="relative truncate">{{ WebDisplay::value($by, $row['value']) }}</span>
                    <span class="relative shrink-0 tabular-nums text-muted">{{ number_format($row['completed']) }} / {{ number_format($row['entered']) }} · {{ WebDisplay::percent($row['rate']) }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">Nobody entered this funnel in the period.</p>
            @endforelse
        </x-website.card>
    @endif
</x-website.layout>
