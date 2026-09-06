@props(['title', 'subtitle' => null])

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.theme-init')
        <title>{{ $title }} · Rezure Telemetry</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-canvas text-foreground antialiased">
        <div data-sidebar-backdrop hidden class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

        <div class="flex min-h-screen">
            <aside
                data-sidebar
                class="fixed inset-y-0 left-0 z-40 flex w-72 max-w-[85vw] -translate-x-full flex-col overflow-y-auto border-r border-border bg-surface transition-transform duration-200 ease-in-out lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:max-w-none lg:translate-x-0 lg:shrink-0"
            >
                <div class="flex items-center justify-between border-b border-border px-5 py-5">
                    <div>
                        <x-rezure-wordmark class="text-base" />
                        <p class="mt-0.5 text-xs text-muted">Telemetry console</p>
                    </div>

                    <button
                        type="button"
                        data-sidebar-toggle
                        aria-label="Close menu"
                        class="rounded-lg border border-border p-1.5 text-muted hover:bg-surface-raised hover:text-foreground lg:hidden"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 space-y-1 px-3 py-4">
                    @php
                        $navItems = [
                            ['route' => 'dashboard.overview', 'label' => 'Overview', 'badge' => $navBadges['overview'] ?? 0],
                            ['route' => 'dashboard.versions', 'label' => 'Versions', 'badge' => $navBadges['versions'] ?? 0],
                            ['route' => 'dashboard.features', 'label' => 'Features', 'badge' => $navBadges['features'] ?? 0],
                            ['route' => 'dashboard.errors', 'label' => 'Errors', 'badge' => $navBadges['errors'] ?? 0],
                            ['route' => 'dashboard.devices', 'label' => 'Devices', 'badge' => $navBadges['devices'] ?? 0],
                            ['route' => 'dashboard.traffic', 'label' => 'Traffic', 'badge' => $navBadges['traffic'] ?? 0],
                            ['route' => 'dashboard.geography', 'label' => 'Geography', 'badge' => $navBadges['geography'] ?? 0],
                            ['route' => 'dashboard.behavior', 'label' => 'Behavior', 'badge' => $navBadges['behavior'] ?? 0],
                            ['route' => 'dashboard.technical', 'label' => 'Technical', 'badge' => $navBadges['technical'] ?? 0],
                            ['route' => 'dashboard.funnel', 'label' => 'Funnel', 'badge' => $navBadges['funnel'] ?? 0],
                            ['route' => 'dashboard.releases', 'label' => 'Releases', 'badge' => $navBadges['releases'] ?? 0],
                            ['route' => 'dashboard.tickets', 'label' => 'Tickets', 'badge' => $navBadges['tickets'] ?? 0],
                            ['route' => 'dashboard.changelog', 'label' => 'Changelog', 'badge' => $navBadges['changelog'] ?? 0],
                        ];
                    @endphp

                    @foreach ($navItems as $item)
                        @php $active = request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*'); @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            class="flex items-center justify-between rounded-lg px-3 py-2 text-sm transition-colors {{ $active ? 'bg-brand text-white' : 'text-muted hover:bg-surface-raised hover:text-foreground' }}"
                        >
                            <span class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full {{ $active ? 'bg-white' : 'bg-subtle' }}"></span>
                                {{ $item['label'] }}
                            </span>
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $active ? 'bg-white/20' : 'bg-surface-raised text-subtle' }}">
                                {{ \App\Support\Formatting::compact($item['badge']) }}
                            </span>
                        </a>
                    @endforeach
                </nav>

                <x-dashboard.ingest-health-card class="m-3" />

                <div class="space-y-2 border-t border-border px-3 py-3">
                    <p class="truncate px-1 text-xs text-muted" title="{{ auth()->user()->email }}">
                        {{ auth()->user()->email }}
                    </p>

                    <x-theme-toggle class="w-full justify-center" />

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-border bg-surface-raised px-2.5 py-1.5 text-xs text-muted transition-colors hover:border-negative/40 hover:bg-negative/10 hover:text-negative"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                                <path d="M16 17l5-5-5-5M21 12H9" />
                            </svg>
                            Sign out
                        </button>
                    </form>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex items-center gap-3 border-b border-border bg-surface px-4 py-3 lg:hidden">
                    <button
                        type="button"
                        data-sidebar-toggle
                        aria-label="Open menu"
                        aria-expanded="false"
                        class="rounded-lg border border-border p-2 text-muted hover:bg-surface-raised hover:text-foreground"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <x-rezure-wordmark class="text-sm" />
                </header>

                <main class="flex-1 overflow-x-hidden">
                    <header class="flex flex-col gap-3 border-b border-border px-4 py-5 sm:px-6 sm:py-6 md:flex-row md:items-start md:justify-between lg:px-8">
                        <div>
                            <h1 class="text-xl font-semibold tracking-tight">{{ $title }}</h1>
                            @if ($subtitle)
                                <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
                            @endif
                        </div>

                        @isset($actions)
                            <div class="flex flex-wrap items-center gap-3">
                                {{ $actions }}
                            </div>
                        @endisset
                    </header>

                    <div class="px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
