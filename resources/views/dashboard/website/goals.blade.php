@php
    use App\Models\WebGoal;
    use App\Support\WebDisplay;

    $form = $editing ?? null;
@endphp

<x-website.layout title="Goals" subtitle="Conversions you care about: an event, or reaching a page. Each goal can also filter the whole dashboard." :filters="$filters">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/40 bg-positive/10 px-4 py-2 text-sm text-positive">{{ session('status') }}</div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <x-website.card title="Goals" class="lg:col-span-2">
            @if (count($goals) === 0)
                <p class="py-6 text-center text-xs text-subtle">No goals yet — add one on the right.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                            <th class="pb-2 font-medium">Goal</th>
                            <th class="pb-2 text-right font-medium">Conversions</th>
                            <th class="pb-2 text-right font-medium">Visitors</th>
                            <th class="pb-2 text-right font-medium">Rate</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($goals as $row)
                            <tr @class(['border-t border-border', 'bg-brand/5' => $selected?->is($row['goal'])])>
                                <td class="py-2 pr-3">
                                    <a href="{{ route('dashboard.website.goals', [...$filters->query(), 'goal' => $row['goal']->id]) }}" class="font-medium hover:text-brand">{{ $row['goal']->name }}</a>
                                    @unless ($row['goal']->is_active)<span class="ml-1 text-xs text-subtle">(paused)</span>@endunless
                                    <p class="font-mono text-xs text-subtle">{{ $row['goal']->type }}: {{ $row['goal']->match }}@if ($row['goal']->label_match) · label {{ $row['goal']->label_match }}@endif</p>
                                </td>
                                <td class="py-2 text-right tabular-nums">{{ number_format($row['conversions']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($row['visitors']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::percent($row['rate'], 2) }}</td>
                                <td class="py-2 text-right text-xs">
                                    <a href="{{ route('dashboard.website.overview', $filters->queryWith('goal', (string) $row['goal']->id)) }}" class="text-brand hover:underline">Filter</a>
                                    · <a href="{{ route('dashboard.website.goals', [...$filters->query(), 'edit' => $row['goal']->id]) }}" class="text-muted hover:text-foreground">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-website.card>

        <x-website.card :title="$form ? 'Edit goal' : 'New goal'">
            <form method="POST" action="{{ $form ? route('dashboard.website.goals.update', $form) : route('dashboard.website.goals.store') }}" class="space-y-3">
                @csrf
                @if ($form) @method('PUT') @endif

                <label class="block text-xs text-muted">Name
                    <input name="name" value="{{ old('name', $form?->name) }}" required maxlength="100" class="mt-1 w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-sm text-foreground focus:border-brand focus:outline-none">
                </label>
                <label class="block text-xs text-muted">Type
                    <select name="type" class="mt-1 w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-sm text-foreground focus:border-brand focus:outline-none">
                        <option value="{{ WebGoal::TYPE_EVENT }}" @selected(old('type', $form?->type) === WebGoal::TYPE_EVENT)>Event happened</option>
                        <option value="{{ WebGoal::TYPE_PAGEVIEW }}" @selected(old('type', $form?->type) === WebGoal::TYPE_PAGEVIEW)>Page visited</option>
                    </select>
                </label>
                <label class="block text-xs text-muted">Event name, or page path (<code>*</code> = wildcard, e.g. <code>/guide/*</code>)
                    <input name="match" value="{{ old('match', $form?->match) }}" required maxlength="512" class="mt-1 w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 font-mono text-sm text-foreground focus:border-brand focus:outline-none">
                </label>
                <label class="block text-xs text-muted">Event label must match (optional, events only)
                    <input name="label_match" value="{{ old('label_match', $form?->label_match) }}" maxlength="255" class="mt-1 w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 font-mono text-sm text-foreground focus:border-brand focus:outline-none">
                </label>
                <label class="flex items-center gap-2 text-xs text-muted">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $form?->is_active ?? true)) class="accent-brand"> Active
                </label>

                @if ($errors->any())
                    <ul class="space-y-1 text-xs text-negative">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                @endif

                <div class="flex items-center gap-2">
                    <button type="submit" class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90">{{ $form ? 'Save' : 'Add goal' }}</button>
                    @if ($form)
                        <a href="{{ route('dashboard.website.goals', $filters->query()) }}" class="text-sm text-muted hover:text-foreground">Cancel</a>
                    @endif
                </div>
            </form>

            @if ($form)
                <form method="POST" action="{{ route('dashboard.website.goals.destroy', $form) }}" class="mt-4 border-t border-border pt-3">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-negative hover:underline">Delete this goal</button>
                </form>
            @endif
        </x-website.card>
    </div>

    @if ($selected)
        <x-website.card :title="$selected->name.' by '.strtolower($breakdowns[$by])" class="mt-4">
            <x-slot:actions>
                @foreach ($breakdowns as $key => $label)
                    <a href="{{ route('dashboard.website.goals', [...$filters->query(), 'goal' => $selected->id, 'by' => $key]) }}" @class([
                        'rounded-md px-2.5 py-1 text-xs',
                        'bg-surface-raised text-foreground' => $by === $key,
                        'text-muted hover:text-foreground' => $by !== $key,
                    ])>{{ $label }}</a>
                @endforeach
            </x-slot:actions>

            @forelse ($breakdown as $row)
                <div class="relative flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                    <span class="absolute inset-y-1 left-0 rounded bg-brand/10" style="width: {{ min(100, $row['rate']) }}%"></span>
                    <span class="relative truncate">{{ WebDisplay::value($by, $row['value']) }}</span>
                    <span class="relative shrink-0 tabular-nums text-muted">{{ number_format($row['conversions']) }} / {{ number_format($row['sessions']) }} · {{ WebDisplay::percent($row['rate'], 2) }}</span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No visits for this period.</p>
            @endforelse
        </x-website.card>
    @endif
</x-website.layout>
