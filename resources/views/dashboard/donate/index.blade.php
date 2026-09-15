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

    @foreach ([
        'local' => ['heading' => 'Local links', 'sub' => 'Trakteer, Saweria, and similar', 'methods' => $local],
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
                                <td class="px-5 py-3 text-foreground">{{ $method->label }}</td>
                                <td class="max-w-md truncate px-5 py-3 text-muted">
                                    {{ $category === 'crypto' ? $method->symbol.' · '.$method->address : $method->url }}
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
