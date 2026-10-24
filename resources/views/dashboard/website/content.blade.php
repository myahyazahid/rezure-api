@php
    use App\Support\WebDisplay;
@endphp

<x-website.layout title="Content" subtitle="Blog posts, documentation, what people search for, and broken links." :filters="$filters">
    <x-website.card title="Blog posts" subtitle="Read-through = the end of the article came into view. First 7 days counts from publish time, so old and new posts compare fairly.">
        @if (count($blog) === 0)
            <p class="py-6 text-center text-xs text-subtle">No published posts.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] uppercase tracking-wide text-subtle">
                            <th class="pb-2 font-medium">Post</th>
                            <th class="pb-2 text-right font-medium">Views</th>
                            <th class="pb-2 text-right font-medium">Readers</th>
                            <th class="pb-2 text-right font-medium">Read time</th>
                            <th class="pb-2 text-right font-medium">Scroll</th>
                            <th class="pb-2 text-right font-medium">Read-through</th>
                            <th class="pb-2 text-right font-medium">First 7 days</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($blog as $post)
                            <tr class="border-t border-border">
                                <td class="max-w-0 py-2 pr-3">
                                    <a href="{{ route('dashboard.website.pages.show', [...$filters->query(), 'path' => $post['path']]) }}" class="block truncate hover:text-brand">{{ $post['title'] }}</a>
                                    <span class="text-xs text-subtle">{{ $post['published_at']->format('d M Y') }}</span>
                                </td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($post['views']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($post['readers']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::duration($post['avg_engaged_seconds']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::percent($post['avg_scroll'], 0) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ WebDisplay::percent($post['read_through_rate']) }}</td>
                                <td class="py-2 text-right tabular-nums text-muted">{{ number_format($post['first_week_views']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-website.card>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <x-website.card title="Documentation pages">
            @include('dashboard.website.partials.pages-table', ['pages' => $docs, 'compact' => true])
        </x-website.card>

        <x-website.card title="Docs searches" subtitle="&quot;Left after&quot; = the search was on the last page of the visit — the docs may not have had the answer.">
            @forelse ($searches as $search)
                <div class="flex items-center justify-between gap-3 border-t border-border py-2 text-sm first:border-t-0">
                    <span class="truncate">“{{ $search['query'] }}”</span>
                    <span class="shrink-0 tabular-nums text-muted">
                        {{ number_format($search['searches']) }}×
                        <span @class(['ml-2', 'text-negative' => $search['exit_rate'] >= 50])>{{ WebDisplay::percent($search['exit_rate'], 0) }} left after</span>
                    </span>
                </div>
            @empty
                <p class="py-6 text-center text-xs text-subtle">No searches in this period.</p>
            @endforelse
        </x-website.card>
    </div>

    <x-website.card title="Not found (404)" subtitle="Broken links: missing paths and the pages that link to them." class="mt-4">
        @forelse ($notFound as $row)
            <div class="border-t border-border py-2.5 text-sm first:border-t-0">
                <div class="flex items-center justify-between gap-3">
                    <span class="truncate font-medium">{{ $row['path'] }}</span>
                    <span class="shrink-0 tabular-nums text-muted">{{ number_format($row['hits']) }} hits</span>
                </div>
                @if (count($row['referrers']) > 0)
                    <p class="mt-1 truncate text-xs text-subtle">from {{ implode(' · ', $row['referrers']) }}</p>
                @endif
            </div>
        @empty
            <p class="py-6 text-center text-xs text-subtle">No 404s in this period. 🎉</p>
        @endforelse
    </x-website.card>
</x-website.layout>
