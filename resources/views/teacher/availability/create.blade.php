@extends('layouts.dashboard')
@section('title', 'Tambah Slot Ketersediaan')
@section('heading', 'Tambah Slot Ketersediaan')
@section('description', 'Isi tanggal, cabang, dan waktu yang tersedia untuk Anda mengajar.')
@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Teacher / Slot Ketersediaan</p><h2>Tambah slot mengajar.</h2><p>Slot berlaku pada tanggal yang Anda pilih.</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.availability.index') }}"><x-dashboard.icon name="back" />Kembali ke slot saya</a></section>
    <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Data slot</h2><p>Kolom bertanda * wajib diisi.</p></div></header>
        @if($branches->isEmpty())<x-dashboard.empty title="Belum ada cabang penugasan aktif" message="Hubungi Admin untuk penugasan siswa, kelas, atau cabang sebelum menambahkan slot." icon="building" />
        @else<div class="dpe-dashboard-card__body"><form method="POST" action="{{ route('teacher.availability.store') }}" class="dpe-dashboard-form dpe-teacher-stack">@csrf @include('teacher.availability._form')</form></div>@endif
    </article>
@endsection
