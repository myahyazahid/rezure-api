@props(['buckets', 'height' => 'h-56'])

{{-- Bucketed distribution, drawn by the device dashboard's bar chart. --}}
<div {{ $attributes->merge(['class' => $height]) }}>
    <canvas
        data-bar-chart
        data-labels="{{ json_encode(array_column($buckets, 'label')) }}"
        data-values="{{ json_encode(array_column($buckets, 'count')) }}"
    ></canvas>
</div>
