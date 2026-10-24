<x-website.layout title="Click heatmap" :subtitle="$path.' · '.number_format($heatmap['total']).' clicks on '.$bucket.' screens'" :filters="$filters">
    <div class="grid gap-4 xl:grid-cols-4">
        <div class="space-y-4">
            <x-website.card title="Page">
                <form method="GET" action="{{ route('dashboard.website.heatmap') }}" class="space-y-3">
                    @include('dashboard.website.partials.filter-inputs')

                    <select name="path" data-autosubmit class="w-full rounded-lg border border-border bg-surface-raised px-3 py-1.5 text-sm focus:border-brand focus:outline-none">
                        @if (! collect($heatmap['pages'])->contains('path', $path))
                            <option value="{{ $path }}" selected>{{ $path }}</option>
                        @endif
                        @foreach ($heatmap['pages'] as $page)
                            <option value="{{ $page['path'] }}" @selected($page['path'] === $path)>{{ $page['path'] }} ({{ number_format($page['clicks']) }})</option>
                        @endforeach
                    </select>

                    {{-- Before the buttons: a clicked button's value comes later and wins. --}}
                    <input type="hidden" name="viewport" value="{{ $bucket }}">

                    <div class="flex gap-1">
                        @foreach ($buckets as $option)
                            <button type="submit" name="viewport" value="{{ $option }}" @class([
                                'flex-1 rounded-md px-2 py-1.5 text-xs font-medium capitalize',
                                'bg-brand text-white' => $option === $bucket,
                                'bg-surface-raised text-muted hover:text-foreground' => $option !== $bucket,
                            ])>{{ $option }}</button>
                        @endforeach
                    </div>
                </form>
            </x-website.card>

            <x-website.card title="Most clicked elements">
                @forelse ($heatmap['elements'] as $element)
                    <div class="border-t border-border py-2 text-sm first:border-t-0">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate">{{ $element['text'] ?: '(no text)' }}</span>
                            <span class="shrink-0 tabular-nums text-muted">{{ number_format($element['clicks']) }}</span>
                        </div>
                        <span class="block truncate font-mono text-[11px] text-subtle">{{ $element['selector'] }}@if ($element['rage'] > 0) · <span class="text-negative">{{ $element['rage'] }} rage</span>@endif</span>
                    </div>
                @empty
                    <p class="py-6 text-center text-xs text-subtle">No clicks recorded.</p>
                @endforelse
            </x-website.card>
        </div>

        <x-website.card class="xl:col-span-3" title="Live page with recorded clicks" subtitle="The current version of the page, so clicks on content that has since moved may not line up.">
            <div data-click-heatmap="{{ json_encode(['width' => $heatmap['width'], 'height' => $heatmap['height'], 'points' => $heatmap['points']]) }}" class="relative w-full overflow-hidden rounded-lg border border-border bg-surface-raised">
                <div data-heatmap-stage class="absolute left-0 top-0 origin-top-left">
                    {{--
                        Fully sandboxed: no scripts, so the website's tracker never runs
                        here. VitePress pages are pre-rendered HTML, so they still show.
                        (A `?rz_ignore=1` URL would instead opt the maintainer's whole
                        browser out — the dashboard and the site share a parent domain,
                        so the iframe's storage is the site's own.)
                    --}}
                    <iframe src="{{ $pageUrl }}" title="{{ $path }}" class="absolute inset-0 border-0 bg-white" loading="lazy" referrerpolicy="no-referrer" sandbox></iframe>
                    <canvas class="pointer-events-none absolute inset-0"></canvas>
                </div>
            </div>
        </x-website.card>
    </div>
</x-website.layout>
