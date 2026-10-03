@props(['device'])

@if ($device)
    <a href="{{ route('dashboard.devices.show', $device) }}" {{ $attributes->merge(['class' => 'group inline-block']) }}>
        @if ($device->device_name)
            <span class="block text-sm font-medium text-foreground group-hover:underline">{{ $device->device_name }}</span>
            <span class="block font-mono text-[11px] text-subtle">{{ $device->short_id }}</span>
        @else
            <span class="block font-mono text-xs text-foreground group-hover:underline">{{ $device->short_id }}</span>
        @endif
    </a>
@else
    <span class="text-xs text-muted">—</span>
@endif
