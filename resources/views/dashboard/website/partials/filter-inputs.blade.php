{{-- Carries the current period/compare/filters through a page's own GET form (search boxes, pickers). --}}
@foreach ($filters->query() as $key => $value)
    @if (is_array($value))
        @foreach ($value as $subKey => $subValue)
            <input type="hidden" name="{{ $key }}[{{ $subKey }}]" value="{{ $subValue }}">
        @endforeach
    @else
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endif
@endforeach
