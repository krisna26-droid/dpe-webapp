@props(['identity', 'homeUrl' => null])
@php
    $brandTitle = trim($identity['title'] ?: 'Dharma Private English');
    $hasEnglishSuffix = str_ends_with(strtolower($brandTitle), ' english');
    $brandMain = $hasEnglishSuffix ? trim(substr($brandTitle, 0, -8)) : $brandTitle;
    $brandSub = $hasEnglishSuffix ? 'English' : 'English learning centre';
@endphp
<a {{ $attributes->class(['brand']) }} href="{{ ($homeUrl ?? route('home').'#beranda') }}" aria-label="{{ $brandTitle }} — Beranda">
    <x-dpe.logo :src="$identity['image'] ?? null" />
    <span class="brand-wordmark">
        <strong>{{ $brandMain }}</strong>
        <span>{{ $brandSub }}</span>
        <span class="sr-only">{{ $brandTitle }}</span>
    </span>
</a>
