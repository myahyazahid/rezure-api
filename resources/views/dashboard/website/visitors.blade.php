@php
    use App\Support\WebDisplay;
@endphp

<x-website.layout title="Visitors" subtitle="Individual visitors with a visit in the period — open one to see everything they did." :filters="$filters">
    <x-website.card>
        <x-slot:actions>
            <form method="GET" action="{{ route('dashboard.website.visitors') }}" class="flex items-center gap-2">
                @include('dashboard.website.partials.filter-inputs')
                <input type="search" name="q" value="{{ $search }}" placeholder="IP, city or visitor id…" class="w-56 rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-sm placeholder:text-subtle focus:border-brand focus:outline-none">
            </form>
        </x-slot:actions>

        @if ($visitors->isEmpty())
            <p class="py-6 text-center text-xs text-subtle">No visitors for this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                            <th class="pb-2 font-medium">Visitor</th>
                            <th class="pb-2 font-medium">Location</th>
                            <th class="pb-2 font-medium">Device</th>
                            <th class="pb-2 font-medium">First came from</th>
                            <th class="pb-2 text-right font-medium">Visits</th>
                            <th class="pb-2 text-right font-medium">Pages</th>
                            <th class="pb-2 text-right font-medium">Downloaded</th>
                            <th class="pb-2 text-right font-medium">Last seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($visitors as $visitor)
                            <tr class="border-t border-border">
                                <td class="py-2 pr-3">
                                    <a href="{{ route('dashboard.website.visitors.show', $visitor) }}" class="font-mono text-xs hover:text-brand">{{ $visitor->short_id }}</a>
                                    <p class="text-xs text-subtle">{{ $visitor->last_ip_address }}</p>
                                </td>
                                <td class="py-2 pr-3">{{ WebDisplay::flag($visitor->last_country_code) }} {{ $visitor->last_city ?? 'Unknown' }}</td>
                                <td class="py-2 pr-3 text-muted">{{ $visitor->last_browser ?? '?' }} · {{ $visitor->last_os ?? '?' }} · {{ $visitor->last_device_type ?? '?' }}</td>
                                <td class="py-2 pr-3 text-muted">{{ $visitor->first_source ?? 'Direct' }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($visitor->sessions_count) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($visitor->pageviews_count) }}</td>
                                <td class="py-2 text-right">
                                    @if ($visitor->first_download_at)
                                        <span class="rounded-full bg-positive/15 px-2 py-0.5 text-xs text-positive">{{ $visitor->device ? 'installed' : 'yes' }}</span>
                                    @else
                                        <span class="text-subtle">–</span>
                                    @endif
                                </td>
                                <td class="py-2 text-right text-xs text-muted" title="{{ $visitor->last_seen_at->setTimezone(config('app.display_timezone'))->format('d M Y H:i') }}">{{ $visitor->last_seen_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $visitors->links() }}</div>
        @endif
    </x-website.card>
</x-website.layout>
