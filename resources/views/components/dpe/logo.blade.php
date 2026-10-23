@props(['src' => null])
@php
    $logoPath = public_path('assets/dpe/images/logo-dpe.png');
    $logoUrl = $src ?: (is_file($logoPath)
        ? asset('assets/dpe/images/logo-dpe.png').'?v='.filemtime($logoPath)
        : null);
@endphp
<span {{ $attributes->class(['dpe-brand-logo']) }} aria-hidden="true">
    @if ($logoUrl)
        <img class="dpe-brand-logo__image" src="{{ $logoUrl }}" width="631" height="395" alt="" decoding="async">
    @else
        <span class="dpe-brand-logo__fallback">DPE</span>
    @endif
</span>
