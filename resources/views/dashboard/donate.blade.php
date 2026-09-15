<x-dashboard-layout title="Donate" subtitle="What the Support Developer page shows in the client — backs GET /api/v1/support/donate.">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    @php
        $old = fn (string $section, int $i, string $field) => old("{$section}.{$i}.{$field}", $config->{$section}[$i][$field] ?? '');
        $rowCount = 5;
    @endphp

    <form method="POST" action="{{ route('dashboard.donate.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-xl border border-border bg-surface p-5">
            <label for="message" class="mb-1 block text-xs font-medium uppercase tracking-wide text-subtle">Message</label>
            <p class="mb-2 text-xs text-muted">Short one-liner shown above the donation sections in the client.</p>
            <textarea
                name="message" id="message" rows="2"
                class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
            >{{ old('message', $config->message) }}</textarea>
            @error('message')
                <p class="mt-1 text-xs text-negative">{{ $message }}</p>
            @enderror
        </div>

        @foreach (['local' => 'Local links (Trakteer, Saweria, ...)', 'global' => 'Global links (GitHub Sponsors, Ko-fi, ...)'] as $section => $heading)
            <div class="rounded-xl border border-border bg-surface p-5">
                <p class="mb-3 text-xs font-medium uppercase tracking-wide text-subtle">{{ $heading }}</p>
                <div class="space-y-3">
                    @for ($i = 0; $i < $rowCount; $i++)
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <input
                                type="text" name="{{ $section }}[{{ $i }}][label]" value="{{ $old($section, $i, 'label') }}"
                                placeholder="Label"
                                class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                            >
                            <input
                                type="text" name="{{ $section }}[{{ $i }}][url]" value="{{ $old($section, $i, 'url') }}"
                                placeholder="https://..."
                                class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                            >
                        </div>
                        @error($section.'.'.$i.'.label')
                            <p class="text-xs text-negative">{{ $message }}</p>
                        @enderror
                        @error($section.'.'.$i.'.url')
                            <p class="text-xs text-negative">{{ $message }}</p>
                        @enderror
                    @endfor
                </div>
                <p class="mt-2 text-xs text-subtle">Leave label + url blank to skip a row.</p>
            </div>
        @endforeach

        <div class="rounded-xl border border-border bg-surface p-5">
            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-subtle">Crypto wallets</p>
            <div class="space-y-3">
                @for ($i = 0; $i < $rowCount; $i++)
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <input
                            type="text" name="crypto[{{ $i }}][symbol]" value="{{ $old('crypto', $i, 'symbol') }}"
                            placeholder="BTC"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >
                        <input
                            type="text" name="crypto[{{ $i }}][label]" value="{{ $old('crypto', $i, 'label') }}"
                            placeholder="Bitcoin"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >
                        <input
                            type="text" name="crypto[{{ $i }}][address]" value="{{ $old('crypto', $i, 'address') }}"
                            placeholder="Wallet address"
                            class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
                        >
                    </div>
                    @error('crypto.'.$i.'.symbol')
                        <p class="text-xs text-negative">{{ $message }}</p>
                    @enderror
                    @error('crypto.'.$i.'.label')
                        <p class="text-xs text-negative">{{ $message }}</p>
                    @enderror
                    @error('crypto.'.$i.'.address')
                        <p class="text-xs text-negative">{{ $message }}</p>
                    @enderror
                @endfor
            </div>
            <p class="mt-2 text-xs text-subtle">Leave symbol + address blank to skip a row. The client generates the QR code itself from the address — never paste a pre-rendered QR image URL here.</p>
        </div>

        <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand/90">
            Save
        </button>
    </form>
</x-dashboard-layout>
