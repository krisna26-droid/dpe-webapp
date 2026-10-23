@extends('layouts.public')
@section('title', 'Program belajar')
@section('content')
<section class="page-heading container"><span class="eyebrow">Program belajar</span><h1>Tujuan berbeda.<br><span class="accent">Langkah yang sesuai.</span></h1><p>Kenali pilihan belajar bahasa Inggris dan konsultasikan program yang sesuai dengan kebutuhanmu.</p></section>
<section class="container catalog-section" aria-label="Daftar program">@if ($site['programs'])<div class="program-grid">@foreach ($site['programs'] as $program)<x-dpe.program-card :program="$program" :index="$loop->index" />@endforeach</div>@else <x-dpe.empty title="Informasi program belum tersedia" />@endif
    <div class="catalog-note"><x-dpe.icon name="message" /><div><h2>Masih ingin mengenali pilihanmu?</h2><p>Kunjungi halaman kontak untuk konsultasi program dan informasi pendaftaran.</p></div><a class="text-link" href="{{ route('public.contact') }}">Lihat kontak <x-dpe.icon name="arrow" /></a></div>
</section>
<x-dpe.cta />
@endsection
