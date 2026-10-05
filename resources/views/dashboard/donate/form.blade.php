@php
    $categoryLabels = ['local' => 'Local link', 'global' => 'Global link', 'crypto' => 'Crypto wallet'];
    $showPicker = ! $method && ! $preset;

    $presetDefault = (! $method && $preset && $preset !== 'custom') ? ($presets[$preset] ?? null) : null;
    $defaultLabel = is_array($presetDefault) ? ($presetDefault['label'] ?? '') : ($presetDefault ?? '');
    $defaultSymbol = is_array($presetDefault) ? ($presetDefault['symbol'] ?? '') : '';
    $defaultNetwork = is_array($presetDefault) ? ($presetDefault['network'] ?? '') : '';
@endphp

<x-dashboard-layout
    title="{{ $method ? 'Edit donate method' : 'Add donate method' }}"
    subtitle="{{ $categoryLabels[$category] ?? $category }} — backs GET /api/v1/support/donate."
>
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    <a href="{{ route('dashboard.donate') }}" class="mb-4 inline-block text-sm text-muted hover:text-foreground">&larr; Back to Donate</a>

    @if ($showPicker)
        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="mb-1 text-sm font-medium uppercase tracking-wide text-subtle">Pick a platform</p>
            <p class="mb-4 text-xs text-muted">Pre-fills the label{{ $category === 'crypto' ? ' and symbol' : '' }} below — everything stays editable. Nothing on the list you want? Use Custom.</p>

            <div class="flex flex-wrap gap-2">
                @foreach ($presets as $key => $preset_)
                    <a
                        href="{{ route('dashboard.donate.create', ['category' => $category, 'preset' => $key]) }}"
                        class="rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground hover:border-brand"
                    >
                        {{ is_array($preset_) ? $preset_['label'] : $preset_ }}
                    </a>
                @endforeach

                <a
                    href="{{ route('dashboard.donate.create', ['category' => $category, 'preset' => 'custom']) }}"
                    class="rounded-lg border border-dashed border-border px-3 py-2 text-sm text-muted hover:border-brand hover:text-foreground"
                >
                    + Custom
                </a>
            </div>
        </div>
    @else
        <form
            method="POST"
            action="{{ $method ? route('dashboard.donate.update', $method) : route('dashboard.donate.store') }}"
            enctype="multipart/form-data"
            class="max-w-lg space-y-4 rounded-xl border border-border bg-surface p-5"
        >
            @csrf
            @if ($method)
                @method('PUT')
            @endif

            <input type="hidden" name="category" value="{{ $category }}">
            <input type="hidden" name="preset" value="{{ $method?->preset ?? $preset }}">

            <div>
                <label for="label" class="mb-1 block text-xs font-medium text-muted">Label</label>
                <input
                    type="text" name="label" id="label" value="{{ old('label', $method->label ?? $defaultLabel) }}"
                    placeholder="{{ $category === 'crypto' ? 'Bitcoin' : 'Trakteer' }}"
                    class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                >
                @error('label')
                    <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                @enderror
            </div>

            @if ($category === 'crypto')
                <div>
                    <label for="symbol" class="mb-1 block text-xs font-medium text-muted">Symbol</label>
                    <input
                        type="text" name="symbol" id="symbol" value="{{ old('symbol', $method->symbol ?? $defaultSymbol) }}"
                        placeholder="BTC"
                        class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                    >
                    @error('symbol')
                        <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="network" class="mb-1 block text-xs font-medium text-muted">Network</label>
                    <input
                        type="text" name="network" id="network" list="crypto-networks"
                        value="{{ old('network', $method->network ?? $defaultNetwork) }}"
                        placeholder="Tron (TRC-20)"
                        class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                    >
                    <datalist id="crypto-networks">
                        @foreach (\App\Support\DonatePresets::networks() as $network)
                            <option value="{{ $network }}"></option>
                        @endforeach
                    </datalist>
                    @error('network')
                        <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-subtle">The chain this address is on — the same coin exists on several, and sending on the wrong one usually loses the funds. Pick one or type your own.</p>
                </div>

                <div>
                    <label for="address" class="mb-1 block text-xs font-medium text-muted">Wallet address</label>
                    <input
                        type="text" name="address" id="address" value="{{ old('address', $method->address ?? '') }}"
                        placeholder="bc1q..."
                        class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 font-mono text-xs text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                    >
                    @error('address')
                        <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-subtle">The client generates the QR code itself from this address — never paste a pre-rendered QR image URL here.</p>
                </div>
            @else
                <div>
                    <label for="url" class="mb-1 block text-xs font-medium text-muted">URL</label>
                    <input
                        type="text" name="url" id="url" value="{{ old('url', $method->url ?? '') }}"
                        placeholder="https://..."
                        class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                    >
                    @error('url')
                        <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            <div>
                <label for="icon" class="mb-1 block text-xs font-medium text-muted">
                    Icon <span class="font-normal text-subtle">(optional{{ $method?->hasIcon() ? ' — leave empty to keep the current one' : '' }})</span>
                </label>

                @if ($method?->hasIcon())
                    <div class="mb-2 flex items-center gap-3">
                        <img
                            src="{{ route('dashboard.donate.icon.preview', ['donateMethod' => $method, 'v' => $method->icon_sha256]) }}" alt="Current icon"
                            class="h-10 w-10 rounded-lg bg-surface-raised object-contain p-1"
                        >
                        <label class="flex items-center gap-2 text-xs text-muted">
                            <input type="checkbox" name="remove_icon" value="1" @checked(old('remove_icon'))>
                            Remove icon
                        </label>
                    </div>
                @endif

                <input
                    type="file" name="icon" id="icon" accept=".svg,.png,.webp,image/svg+xml,image/png,image/webp"
                    class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-raised file:px-3 file:py-2 file:text-sm file:text-foreground"
                >
                @error('icon')
                    <p class="mt-1 text-xs text-negative">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-subtle">
                    PNG, WebP or SVG, up to {{ intdiv(\App\Models\DonateMethod::MAX_ICON_BYTES, 1024) }} KB — a coin, GitHub's mark, and so on.
                    An SVG can't contain scripts, event handlers or a DOCTYPE.
                </p>
            </div>

            <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand/90">
                {{ $method ? 'Save changes' : 'Add method' }}
            </button>
        </form>
    @endif
</x-dashboard-layout>
