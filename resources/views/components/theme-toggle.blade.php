{{-- Shows the active theme; icon visibility is driven by `.dark` on <html> — see resources/css/app.css. --}}
<button
    type="button"
    data-theme-toggle
    aria-label="Toggle light and dark theme"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface-raised px-2.5 py-1.5 text-xs text-muted transition-colors hover:text-foreground']) }}
>
    <svg data-icon-light xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="4" />
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41" />
    </svg>

    <svg data-icon-dark xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
    </svg>

    <span data-icon-light>Light</span>
    <span data-icon-dark>Dark</span>
</button>
