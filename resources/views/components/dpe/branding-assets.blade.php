@php($brandingVersion = filemtime(public_path('assets/dpe/branding.css')))
<link rel="stylesheet" href="{{ asset('assets/dpe/branding.css') }}?v={{ $brandingVersion }}">
