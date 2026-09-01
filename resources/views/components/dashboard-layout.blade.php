@props(['title', 'subtitle' => null])

<!DOCTYPE html>
<html lang="en" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · Rezure Telemetry</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-canvas text-white antialiased">
        <div class="flex min-h-screen">
            <aside class="flex w-64 shrink-0 flex-col border-r border-border bg-surface">
                <div class="border-b border-border px-5 py-5">
                    <p class="text-base font-semibold tracking-tight">Rezure</p>
                    <p class="text-xs text-muted">Telemetry console</p>
                </div>

                <nav class="flex-1 space-y-1 px-3 py-4">
                    @php
                        $navItems = [
                            ['route' => 'dashboard.overview', 'label' => 'Overview', 'badge' => $navBadges['overview'] ?? 0],
                            ['route' => 'dashboard.versions', 'label' => 'Versions', 'badge' => $navBadges['versions'] ?? 0],
                            ['route' => 'dashboard.features', 'label' => 'Features', 'badge' => $navBadges['features'] ?? 0],
                            ['route' => 'dashboard.errors', 'label' => 'Errors', 'badge' => $navBadges['errors'] ?? 0],
                            ['route' => 'dashboard.devices', 'label' => 'Devices', 'badge' => $navBadges['devices'] ?? 0],
                            ['route' => 'dashboard.releases', 'label' => 'Releases', 'badge' => $navBadges['releases'] ?? 0],
                            ['route' => 'dashboard.tickets', 'label' => 'Tickets', 'badge' => $navBadges['tickets'] ?? 0],
                            ['route' => 'dashboard.changelog', 'label' => 'Changelog', 'badge' => $navBadges['changelog'] ?? 0],
                        ];
                    @endphp

                    @foreach ($navItems as $item)
                        @php $active = request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*'); @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            class="flex items-center justify-between rounded-lg px-3 py-2 text-sm transition-colors {{ $active ? 'bg-brand text-white' : 'text-muted hover:bg-surface-raised hover:text-white' }}"
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
            </aside>

            <main class="flex-1 overflow-x-hidden">
                <header class="flex items-start justify-between border-b border-border px-8 py-6">
                    <div>
                        <h1 class="text-xl font-semibold tracking-tight">{{ $title }}</h1>
                        @if ($subtitle)
                            <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
                        @endif
                    </div>

                    @isset($actions)
                        <div class="flex items-center gap-3">
                            {{ $actions }}
                        </div>
                    @endisset
                </header>

                <div class="px-8 py-6">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
