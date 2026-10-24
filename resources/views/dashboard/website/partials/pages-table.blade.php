{{--
    Per-page table shared by Overview (compact) and Pages (full). Rows come
    from WebAnalyticsQuery::pages(); every path links to its detail page.
--}}
@php
    use App\Support\WebDisplay;

    $compact = $compact ?? false;
    $entries = $entries ?? false;
@endphp

@if (count($pages) === 0)
    <p class="py-6 text-center text-xs text-subtle">No pageviews for this period.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                    <th class="pb-2 font-medium">Page</th>
                    @if ($entries)
                        <th class="w-20 pb-2 text-right font-medium">Entries</th>
                        <th class="w-20 pb-2 text-right font-medium">Bounce</th>
                    @else
                        <th class="w-20 pb-2 text-right font-medium">Views</th>
                        <th class="w-20 pb-2 text-right font-medium">Visitors</th>
                    @endif
                    @unless ($compact)
                        <th class="w-20 pb-2 text-right font-medium">Entries</th>
                        <th class="w-20 pb-2 text-right font-medium">Exit rate</th>
                        <th class="w-20 pb-2 text-right font-medium">Bounce</th>
                        <th class="w-20 pb-2 text-right font-medium">Engaged</th>
                        <th class="w-20 pb-2 text-right font-medium">Scroll</th>
                    @endunless
                </tr>
            </thead>
            <tbody>
                @foreach ($pages as $page)
                    <tr class="border-t border-border">
                        <td class="max-w-0 py-2 pr-3">
                            <a href="{{ route('dashboard.website.pages.show', [...$filters->query(), 'path' => $page['path']]) }}" class="block truncate hover:text-brand" title="{{ $page['path'] }}">
                                {{ $page['path'] }}
                                @if ($page['not_found'] > 0)
                                    <span class="ml-1 rounded bg-negative/15 px-1 text-[10px] text-negative">404</span>
                                @endif
                            </a>
                        </td>
                        @if ($entries)
                            <td class="py-2 text-right tabular-nums text-muted">{{ number_format($page['entries']) }}</td>
                            <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::percent($page['bounce_rate']) }}</td>
                        @else
                            <td class="py-2 text-right tabular-nums text-muted">{{ number_format($page['pageviews']) }}</td>
                            <td class="py-2 text-right tabular-nums text-muted">{{ number_format($page['visitors']) }}</td>
                        @endif
                        @unless ($compact)
                            <td class="py-2 text-right tabular-nums text-muted">{{ number_format($page['entries']) }}</td>
                            <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::percent($page['exit_rate']) }}</td>
                            <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::percent($page['bounce_rate']) }}</td>
                            <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::duration($page['avg_engaged_seconds']) }}</td>
                            <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::percent($page['avg_scroll'], 0) }}</td>
                        @endunless
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
