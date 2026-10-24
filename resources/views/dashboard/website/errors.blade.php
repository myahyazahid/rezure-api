<x-website.layout title="Website errors" subtitle="Uncaught JavaScript errors in visitors' browsers, grouped by message and location." :filters="$filters">
    <x-website.card>
        @if (count($errorGroups) === 0)
            <p class="py-6 text-center text-xs text-subtle">No JavaScript errors in this period.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                            <th class="pb-2 font-medium">Error</th>
                            <th class="pb-2 text-right font-medium">Count</th>
                            <th class="pb-2 text-right font-medium">Visits</th>
                            <th class="pb-2 text-right font-medium">First seen</th>
                            <th class="pb-2 text-right font-medium">Last seen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($errorGroups as $error)
                            <tr class="border-t border-border">
                                <td class="max-w-0 py-2 pr-3">
                                    <a href="{{ route('dashboard.website.errors.show', [$error['fingerprint'], ...$filters->query()]) }}" class="block truncate font-medium text-negative hover:underline" title="{{ $error['message'] }}">{{ $error['message'] }}</a>
                                    <span class="block truncate text-xs text-subtle">{{ $error['source'] ?? 'unknown source' }}{{ $error['line'] !== null ? ':'.$error['line'] : '' }} · {{ $error['top_path'] }}</span>
                                </td>
                                <td class="py-2 text-right tabular-nums">{{ number_format($error['occurrences']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($error['sessions']) }}</td>
                                <td class="py-2 text-right text-xs text-muted">{{ \Illuminate\Support\Carbon::parse($error['first_seen'])->diffForHumans() }}</td>
                                <td class="py-2 text-right text-xs text-muted">{{ \Illuminate\Support\Carbon::parse($error['last_seen'])->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-website.card>
</x-website.layout>
