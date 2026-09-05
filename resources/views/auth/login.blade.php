<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.theme-init')
        <title>Sign in · Rezure Telemetry</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-canvas p-4 text-foreground antialiased">
        <div class="absolute right-4 top-4">
            <x-theme-toggle />
        </div>

        <div class="grid w-full max-w-4xl overflow-hidden rounded-2xl border border-border bg-surface shadow-2xl md:grid-cols-2">
            {{-- Form side --}}
            <div class="flex flex-col justify-center px-8 py-12 sm:px-12">
                <p class="text-center text-xs font-semibold uppercase tracking-[0.2em] text-subtle">Welcome to</p>

                <div class="mt-3 flex justify-center">
                    <x-rezure-wordmark class="text-3xl" />
                </div>

                <p class="mx-auto mt-3 max-w-xs text-center text-sm text-muted">
                    Sign in to the telemetry console to track installs, releases, and reported errors.
                </p>

                @if ($errors->any())
                    <div class="mt-6 rounded-lg border border-negative/30 bg-negative/10 px-4 py-3 text-sm text-negative">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-7 space-y-3">
                    @csrf

                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-subtle">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                <circle cx="12" cy="7" r="4" />
                            </svg>
                        </span>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="Email"
                            class="w-full rounded-full border border-border bg-surface-raised py-2.5 pl-11 pr-4 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >
                    </div>

                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-subtle">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                        </span>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Password"
                            class="w-full rounded-full border border-border bg-surface-raised py-2.5 pl-11 pr-4 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >
                    </div>

                    <label class="flex items-center gap-2 pl-1 text-xs text-muted">
                        <input type="checkbox" name="remember" class="rounded border-border bg-surface-raised">
                        Remember me
                    </label>

                    <button
                        type="submit"
                        class="w-full rounded-full bg-brand px-4 py-2.5 text-sm font-semibold uppercase tracking-wide text-white transition-colors hover:bg-brand/90"
                    >
                        Sign in
                    </button>
                </form>

                <p class="mt-6 text-center text-xs text-subtle">
                    Accounts are provisioned by a maintainer — there's no self sign-up.
                </p>
            </div>

            {{--
                Brand side — a fixed light "window" card, deliberately not
                theme-tokened: it's product imagery (mirrors the desktop app's
                own light window chrome), not page content, so it stays white
                whether the dashboard is in light or dark mode.
            --}}
            <div class="relative hidden flex-col overflow-hidden bg-white p-7 md:flex">
                {{-- Decorative red blobs, behind everything else. --}}
                <div class="pointer-events-none absolute -right-14 -top-16 h-52 w-52 rounded-full bg-brand/5"></div>
                <div class="pointer-events-none absolute -bottom-20 -left-12 h-48 w-48 rounded-full bg-brand/5"></div>
                <div class="pointer-events-none absolute right-10 top-1/3 h-2 w-2 rounded-full bg-brand/30"></div>
                <div class="pointer-events-none absolute bottom-12 left-10 h-1.5 w-1.5 rounded-full bg-brand/30"></div>

                {{-- Inset frame with corner accents, like a viewfinder. --}}
                <div class="pointer-events-none absolute inset-4 rounded-xl border border-brand/15"></div>
                <span class="pointer-events-none absolute left-4 top-4 h-5 w-5 rounded-tl-lg border-l-2 border-t-2 border-brand"></span>
                <span class="pointer-events-none absolute right-4 top-4 h-5 w-5 rounded-tr-lg border-r-2 border-t-2 border-brand"></span>
                <span class="pointer-events-none absolute bottom-4 left-4 h-5 w-5 rounded-bl-lg border-b-2 border-l-2 border-brand"></span>
                <span class="pointer-events-none absolute bottom-4 right-4 h-5 w-5 rounded-br-lg border-b-2 border-r-2 border-brand"></span>

                {{-- Window chrome dots. --}}
                <div class="relative flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-[#ff5f57]"></span>
                    <span class="h-3 w-3 rounded-full bg-[#febc2e]"></span>
                    <span class="h-3 w-3 rounded-full bg-[#28c840]"></span>
                </div>

                <div class="relative flex flex-1 flex-col items-center justify-center text-center">
                    <img src="{{ asset('images/rezure-logo.png') }}" alt="Rezure" class="h-24 w-24">
                    <p class="mt-5 text-2xl font-semibold tracking-tight text-neutral-900">Rezure</p>
                    <p class="mx-auto mt-2 max-w-60 text-sm text-neutral-500">
                        Telemetry console for the Rezure desktop app.
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>
