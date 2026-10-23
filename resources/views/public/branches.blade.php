@extends('layouts.public')
@section('title', 'Cabang DPE')
@section('content')
<section class="page-heading container"><span class="eyebrow">Cabang DPE</span><h1>Temukan tempat<br><span class="accent">untuk langkah belajarmu.</span></h1><p>Kenali lokasi cabang, alamat, dan informasi pelayanan yang tersedia.</p></section>
<section class="container catalog-section" aria-label="Daftar cabang" data-branch-directory>
    @if ($site['branches'])<div class="directory-toolbar"><p data-branch-count aria-live="polite">{{ count($site['branches']) }} cabang ditampilkan</p><label class="search-field"><x-dpe.icon name="search" /><span class="sr-only">Cari nama atau alamat cabang</span><input type="search" placeholder="Cari nama atau alamat cabang…" data-branch-search autocomplete="off"></label></div>
    <div class="branch-grid">@foreach ($site['branches'] as $branch)<article class="branch-card" data-branch-card data-search="{{ $branch['name'].' '.$branch['address'] }}">
        @if ($branch['image'])<img class="branch-image" src="{{ $branch['image'] }}" alt="Cabang {{ $branch['name'] }}" width="600" height="360" loading="lazy">@endif
        <div class="branch-card-heading"><span class="icon-tile"><x-dpe.icon name="pin" /></span><span class="tag">Cabang DPE</span></div><h2>{{ $branch['name'] }}</h2>
        @if ($branch['description'])<p class="prose">{{ $branch['description'] }}</p>@endif
        <div class="branch-meta"><x-dpe.icon name="pin" /><p>{{ $branch['address'] ?: 'Alamat belum tersedia' }}</p></div><div class="branch-meta"><x-dpe.icon name="clock" /><p>{{ $branch['service_hours'] ?: 'Jam pelayanan belum tersedia' }}</p></div>
        <div class="branch-actions">@if ($branch['map_url'])<a class="button button-outline" href="{{ $branch['map_url'] }}" target="_blank" rel="noopener noreferrer">Lihat peta <x-dpe.icon name="external" /></a>@endif <a class="text-link" href="{{ $branch['whatsapp_url'] ?: route('public.contact') }}" @if ($branch['whatsapp_url']) target="_blank" rel="noopener noreferrer" @endif>Hubungi cabang <x-dpe.icon name="arrow" /></a></div>
    </article>@endforeach</div>
    <x-dpe.empty data-branch-empty hidden title="Cabang tidak ditemukan" description="Coba nama atau alamat lain, atau kosongkan pencarian untuk melihat semua cabang." icon="search" />
    @else <x-dpe.empty title="Informasi cabang belum tersedia" icon="pin" />@endif
</section>
<x-dpe.cta />
@endsection
