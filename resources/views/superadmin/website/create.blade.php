@extends('layouts.dashboard')
@section('title', 'Tambah Konten Website')
@section('heading', 'Tambah Konten Website')
@section('description', 'Pilih bagian tambahan yang ingin disiapkan sebagai draft.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/website-content.css') }}?v={{ filemtime(public_path('assets/dpe/website-content.css')) }}">
@endpush
@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><h2>Pilih jenis konten.</h2><p>Program dan cabang ditambahkan melalui menu data operasional masing-masing.</p></div><div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.website.index') }}"><x-dashboard.icon name="back" />Kembali ke Konten Publik</a></div></section>
    <div class="dpe-dashboard-grid">
        @foreach (\App\Services\WebsiteContentDefinition::REPEATED as $kind => $label)<article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>{{ $label }}</h2><p>{{ $kind === 'home.benefit' ? 'Tambahkan poin keunggulan pada beranda.' : 'Tambahkan informasi cara belajar pada bagian Tentang.' }}</p></div></header><div class="dpe-dashboard-card__body"><a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.website.create', ['kind' => $kind]) }}">Siapkan draft</a></div></article>@endforeach
    </div>
@endsection
