@php
    $cssVersion = is_file(public_path('assets/dpe/site.css')) ? filemtime(public_path('assets/dpe/site.css')) : 1;
    $jsVersion = is_file(public_path('assets/dpe/site.js')) ? filemtime(public_path('assets/dpe/site.js')) : 1;
@endphp
<link rel="stylesheet" href="{{ asset('assets/dpe/site.css') }}?v={{ $cssVersion }}">
<x-dpe.branding-assets />
<script src="{{ asset('assets/dpe/site.js') }}?v={{ $jsVersion }}" defer></script>
