<x-website.layout title="Events" subtitle="Actions visitors take: outbound links, CTA clicks, code copies, searches, read-throughs — and downloads." :filters="$filters">
    <x-slot:actions>
        <a href="{{ route('dashboard.website.export', [...$filters->query(), 'report' => 'events']) }}" class="rounded-lg border border-border bg-surface px-3 py-1.5 text-sm text-muted hover:text-foreground">Export CSV</a>
    </x-slot:actions>

    <div class="grid gap-4 lg:grid-cols-5">
        <x-website.card title="All events" class="lg:col-span-2">
            @if (count($events) === 0)
                <p class="py-6 text-center text-xs text-subtle">No events in this period.</p>
            @else
                <table class="w-full table-fixed text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                            <th class="pb-2 font-medium">Event</th>
                            <th class="w-16 pb-2 text-right font-medium">Total</th>
                            <th class="w-16 pb-2 text-right font-medium">Visitors</th>
                            <th class="w-16 pb-2 text-right font-medium">/ visit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            <tr @class(['border-t border-border', 'bg-brand/5' => $event['name'] === $selected])>
                                <td class="truncate py-2 pr-3">
                                    <a href="{{ route('dashboard.website.events', [...$filters->query(), 'event' => $event['name']]) }}" @class(['hover:text-brand', 'font-medium text-brand' => $event['name'] === $selected])>{{ $event['name'] }}</a>
                                </td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($event['total']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($event['visitors']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ $event['per_session'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-website.card>

        <div class="space-y-4 lg:col-span-3">
            @if ($detail)
                <x-website.card :title="$selected.' — labels'" subtitle="The main value of each event (the outbound URL, the search query, the version…).">
                    @forelse ($detail['labels'] as $label)
                        <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                            <span class="truncate" title="{{ $label['label'] }}">{{ $label['label'] }}</span>
                            <span class="shrink-0 tabular-nums text-muted">{{ number_format($label['total']) }} · {{ number_format($label['visitors']) }} visitors</span>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-subtle">No labels.</p>
                    @endforelse
                </x-website.card>

                @foreach ($detail['props'] as $prop => $values)
                    <x-website.card :title="$selected.' · '.$prop">
                        @foreach ($values as $value)
                            <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                                <span class="truncate">{{ $value['value'] }}</span>
                                <span class="tabular-nums text-muted">{{ number_format($value['total']) }}</span>
                            </div>
                        @endforeach
                    </x-website.card>
                @endforeach
            @endif
        </div>
    </div>
</x-website.layout>
