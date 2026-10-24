<x-website.layout title="Funnels" subtitle="Multi-step paths — see where visitors drop off on the way to a download." :filters="$filters">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/40 bg-positive/10 px-4 py-2 text-sm text-positive">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <x-website.card title="Saved funnels">
            @forelse ($funnels as $funnel)
                <a href="{{ route('dashboard.website.funnels.show', [$funnel, ...$filters->query()]) }}" class="block border-t border-border py-2.5 first:border-t-0 hover:text-brand">
                    <span class="text-sm font-medium">{{ $funnel->name }}</span>
                    <span class="block text-xs text-subtle">{{ count($funnel->steps) }} steps · per {{ $funnel->scope }}@if ($funnel->scope === 'visitor') within {{ $funnel->window_hours }}h @endif</span>
                </a>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No funnels yet.</p>
            @endforelse
        </x-website.card>

        <x-website.card title="New funnel" subtitle="Steps must happen in this order. Paths take * as a wildcard." class="lg:col-span-2">
            <form method="POST" action="{{ route('dashboard.website.funnels.store') }}" class="space-y-3">
                @csrf
                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="block text-xs text-muted sm:col-span-1">Name
                        <input name="name" value="{{ old('name') }}" required maxlength="100" placeholder="Landing → download" class="mt-1 w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-sm text-foreground focus:border-brand focus:outline-none">
                    </label>
                    <label class="block text-xs text-muted">Counted per
                        <select name="scope" class="mt-1 w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-sm text-foreground focus:border-brand focus:outline-none">
                            <option value="session" @selected(old('scope') !== 'visitor')>Visit (one session)</option>
                            <option value="visitor" @selected(old('scope') === 'visitor')>Visitor (across visits)</option>
                        </select>
                    </label>
                    <label class="block text-xs text-muted">Window (hours, visitor scope)
                        <input type="number" name="window_hours" value="{{ old('window_hours', 24) }}" min="1" max="720" class="mt-1 w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-sm text-foreground focus:border-brand focus:outline-none">
                    </label>
                </div>

                <div class="space-y-2">
                    @for ($index = 0; $index < 6; $index++)
                        <div class="grid grid-cols-[2rem_8rem_1fr_10rem] items-center gap-2">
                            <span class="text-center text-xs text-subtle">{{ $index + 1 }}</span>
                            @php $stepType = old("steps.$index.type", $index === 2 ? 'event' : 'pageview'); @endphp
                            <select name="steps[{{ $index }}][type]" class="rounded-lg border border-border bg-surface-raised px-2 py-1.5 text-sm text-foreground focus:border-brand focus:outline-none">
                                <option value="pageview" @selected($stepType !== 'event')>Visit page</option>
                                <option value="event" @selected($stepType === 'event')>Event</option>
                            </select>
                            <input name="steps[{{ $index }}][match]" value="{{ old("steps.$index.match", [0 => '/', 1 => '/download', 2 => 'download'][$index] ?? '') }}" placeholder="{{ $index < 2 ? '/path or event_name' : 'optional' }}" class="rounded-lg border border-border bg-surface-raised px-3 py-1.5 font-mono text-sm text-foreground focus:border-brand focus:outline-none">
                            <input name="steps[{{ $index }}][label]" value="{{ old("steps.$index.label") }}" placeholder="event label (opt.)" class="rounded-lg border border-border bg-surface-raised px-3 py-1.5 font-mono text-sm text-foreground focus:border-brand focus:outline-none">
                        </div>
                    @endfor
                    <p class="text-xs text-subtle">Default example: home → download page → download. Leave rows empty to use fewer steps.</p>
                </div>

                @if ($errors->any())
                    <ul class="space-y-1 text-xs text-negative">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                @endif

                <button type="submit" class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90">Save and open</button>
            </form>
        </x-website.card>
    </div>
</x-website.layout>
