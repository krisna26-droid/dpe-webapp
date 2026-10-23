@extends('layouts.dashboard')

@section('title', 'Detail Wali Siswa')
@section('heading', 'Detail Wali Siswa')
@section('description', 'Periksa kontak wali dan status penerima rapor siswa.')

@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Siswa / Wali</p><h2>{{ $guardian->full_name }}</h2><p>{{ $guardian->student?->full_name ?: 'Siswa belum tersedia' }} · {{ $guardian->relationship_name ?: 'Hubungan belum diisi' }}</p></div><div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.guardians.edit', $guardian) }}"><x-dashboard.icon name="settings" /><span>Edit wali</span></a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.students.show', $guardian->student) }}"><x-dashboard.icon name="student" /><span>Profil siswa</span></a></div></section>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Ringkasan kontak</h2><p>Kontak ini digunakan untuk komunikasi dan pembagian rapor.</p></div><span class="dpe-dashboard-badge {{ $guardian->is_primary ? 'dpe-dashboard-badge--success' : '' }}">{{ $guardian->is_primary ? 'Penerima utama' : 'Kontak alternatif' }}</span></header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Nama wali</p><p class="dpe-dashboard-detail__value">{{ $guardian->full_name }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Siswa</p><p class="dpe-dashboard-detail__value"><a href="{{ route('superadmin.students.show', $guardian->student) }}">{{ $guardian->student?->full_name ?: '—' }}</a></p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Hubungan</p><p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $guardian->relationship_name ?: 'Belum diisi' }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">WhatsApp</p><p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $guardian->whatsapp_number }}</p></div>
            </div>
            <div class="dpe-dashboard-form-actions">
                <form method="POST" action="{{ route('superadmin.guardians.destroy', $guardian) }}">
                    @csrf
                    @method('DELETE')
                    <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit"><x-dashboard.icon name="warning" /><span>Hapus kontak</span></button>
                </form>
            </div>
        </div>
    </article>
@endsection
