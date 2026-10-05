<x-dashboard-layout title="Donate" subtitle="What the Support Developer page shows in the client — backs GET /api/v1/support/donate.">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-positive/30 bg-positive/10 px-4 py-2.5 text-sm text-positive">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-xl border border-border bg-surface p-5">
        <form method="POST" action="{{ route('dashboard.donate.message.update') }}" class="space-y-2">
            @csrf
            @method('PUT')

            <label for="message" class="block text-xs font-medium uppercase tracking-wide text-subtle">Message</label>
            <p class="text-xs text-muted">Short one-liner shown above the donation sections in the client.</p>
            <textarea
                name="message" id="message" rows="2"
                class="w-full rounded-lg border border-border bg-surface-raised px-3 py-2 text-sm text-foreground placeholder:text-subtle focus:border-brand focus:outline-none"
            >{{ old('message', $config->message) }}</textarea>
            @error('message')
                <p class="text-xs text-negative">{{ $message }}</p>
            @enderror

            <button type="submit" class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90">
                Save message
            </button>
        </form>
    </div>

    <div class="mt-4 rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-medium uppercase tracking-wide text-subtle">QRIS <span class="normal-case tracking-normal">(optional)</span></p>
        <p class="mt-0.5 text-xs text-muted">
            A QRIS image the client shows for scanning. Leave it empty and the client simply hides the section.
        </p>

        <div class="mt-4 flex flex-wrap items-start gap-5">
            @if ($config->hasQris())
                <img
                    src="{{ route('dashboard.donate.qris.preview', ['v' => $config->qris_sha256]) }}" alt="Current QRIS"
                    class="h-40 w-40 rounded-lg border border-border bg-white object-contain p-1"
                >
            @endif

            <div class="min-w-0 flex-1 space-y-2">
                <form method="POST" action="{{ route('dashboard.donate.qris.update') }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    @method('PUT')

                    <label for="qris" class="block text-xs font-medium text-muted">
                        {{ $config->hasQris() ? 'Replace image' : 'Upload image' }}
                    </label>
                    <input
                        type="file" name="qris" id="qris" accept=".png,.jpg,.jpeg,.webp,image/png,image/jpeg,image/webp"
                        class="block w-full text-sm text-muted file:mr-3 file:rounded-lg file:border-0 file:bg-surface-raised file:px-3 file:py-2 file:text-sm file:text-foreground"
                    >
                    @error('qris')
                        <p class="text-xs text-negative">{{ $message }}</p>
                    @enderror
                    <p class="text-xs text-subtle">
                        PNG, JPEG or WebP, up to {{ intdiv(\App\Models\DonateConfig::MAX_QRIS_BYTES, 1024) }} KB.
                    </p>

                    <button type="submit" class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90">
                        {{ $config->hasQris() ? 'Replace QRIS' : 'Upload QRIS' }}
                    </button>
                </form>

                @if ($config->hasQris())
                    <form method="POST" action="{{ route('dashboard.donate.qris.destroy') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-medium text-negative hover:underline">Remove QRIS</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    @foreach ([
        'local' =>['heading' => 'Local links', 'sub' => 'Trakteer, Saweria, and similar', 'methods' => $local],
        'global' => ['heading' => 'Global links', 'sub' => 'GitHub Sponsors, Ko-fi, and similar', 'methods' => $global],
        'crypto' => ['heading' => 'Crypto wallets', 'sub' => 'The client generates each QR code itself from the address', 'methods' => $crypto],
    ] as $category => $section)
        <div class="mt-4 rounded-xl border border-border bg-surface">
            <div class="flex items-center justify-between px-5 py-4">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wide text-subtle">{{ $section['heading'] }}</p>
                    <p class="mt-0.5 text-xs text-muted">{{ $section['sub'] }}</p>
                </div>
                <a
                    href="{{ route('dashboard.donate.create', ['category' => $category]) }}"
                    class="rounded-lg bg-brand px-3 py-1.5 text-sm font-medium text-white hover:bg-brand/90"
                >
                    + Add
                </a>
            </div>

            <div class="overflow-x-auto border-t border-border">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-border">
                        @forelse ($section['methods'] as $method)
                            <tr>
                                <td class="px-5 py-3 text-foreground">
                                    <span class="flex items-center gap-2">
                                        @if ($method->hasIcon())
                                            <img
                                                src="{{ route('dashboard.donate.icon.preview', ['donateMethod' => $method, 'v' => $method->icon_sha256]) }}" alt=""
                                                class="h-6 w-6 rounded object-contain"
                                            >
                                        @endif
                                        {{ $method->label }}
                                    </span>
                                </td>
                                <td class="max-w-md truncate px-5 py-3 text-muted">
                                    {{ $category === 'crypto' ? collect([$method->symbol, $method->network, $method->address])->filter()->implode(' · ') : $method->url }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <a href="{{ route('dashboard.donate.edit', $method) }}" class="text-xs font-medium text-brand hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('dashboard.donate.destroy', $method) }}" class="ml-3 inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-negative hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="px-5 py-4 text-sm text-subtle" colspan="3">Nothing added yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</x-dashboard-layout>
