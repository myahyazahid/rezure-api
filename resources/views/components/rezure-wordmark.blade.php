{{--
    The logo mark stands in for the capital R, so the text spells "ezure".

    Uses the tight-cropped mark (rezure-logo.png carries ~20% transparent
    padding, which would open a gap mid-word) sized in em and baseline-aligned:
    an <img>'s baseline is its bottom edge, so the mark lands on the text
    baseline at roughly cap height and scales with whatever text size the
    parent sets. role="img" keeps it announcing as "Rezure", not "R ezure".
--}}
<span
    role="img"
    aria-label="Rezure"
    {{ $attributes->merge(['class' => 'inline-flex items-baseline gap-[0.03em] font-semibold tracking-tight']) }}
>
    <img
        src="{{ asset('images/rezure-mark.png') }}"
        alt=""
        aria-hidden="true"
        class="h-[0.72em] w-auto"
    >
    <span aria-hidden="true" class="text-brand">ezure</span>
</span>
