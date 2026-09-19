<x-dashboard-layout title="Whats App" subtitle="Embedded Go WhatsApp console">
    <x-slot:actions>
        <a
            href="https://gowa.redscale.my.id"
            target="_blank"
            rel="noreferrer"
            class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-surface px-3 py-1.5 text-xs font-medium text-muted transition-colors hover:bg-surface-raised hover:text-foreground"
        >
            <span>Open in new tab</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
                <polyline points="15 3 21 3 21 9" />
                <line x1="10" y1="14" x2="21" y2="3" />
            </svg>
        </a>
    </x-slot:actions>

    <div class="overflow-hidden rounded-xl border border-border bg-surface shadow-sm">
        <div class="h-[calc(100vh-14rem)] min-h-[650px] w-full">
            <iframe
                src="https://gowa.redscale.my.id"
                title="Whats App"
                class="h-full w-full border-0"
                allow="camera; microphone; clipboard-read; clipboard-write;"
                loading="lazy"
            ></iframe>
        </div>
    </div>
</x-dashboard-layout>
