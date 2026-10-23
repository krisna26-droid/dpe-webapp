@php($active = $enrollment->isActiveOn())
@extends('layouts.dashboard')

@section('title', 'Detail Pendaftaran Siswa')
@section('heading', 'Detail Pendaftaran Siswa')
@section('description', 'Periksa penempatan program dan cabang siswa.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Siswa / Pendaftaran</p><h2>{{ $enrollment->student?->full_name ?: 'Siswa' }}</h2><p>{{ $enrollment->program?->name ?: 'Program belum tersedia' }} · {{ $enrollment->branch?->name ?: 'Cabang belum tersedia' }}</p></div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.student-enrollments.edit', $enrollment) }}"><x-dashboard.icon name="settings" /><span>Edit pendaftaran</span></a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.students.show', $enrollment->student) }}"><x-dashboard.icon name="student" /><span>Profil siswa</span></a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.students.index') }}"><x-dashboard.icon name="back" /><span>Kembali ke Siswa &amp; Pendaftaran</span></a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Ringkasan pendaftaran</h2><p>Data ini menjadi acuan program dan cabang siswa.</p></div><span class="dpe-dashboard-badge {{ $active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $active ? 'Aktif' : 'Selesai' }}</span></header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Siswa</p><p class="dpe-dashboard-detail__value">{{ $enrollment->student?->full_name ?: '—' }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Akun portal</p><p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $enrollment->student?->portalUser?->username ?: '—' }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Program</p><p class="dpe-dashboard-detail__value">{{ $enrollment->program?->name ?: '—' }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Cabang</p><p class="dpe-dashboard-detail__value">{{ $enrollment->branch?->name ?: '—' }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Tanggal mulai</p><p class="dpe-dashboard-detail__value">{{ $enrollment->starts_on?->format('d M Y') ?: '—' }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Tanggal berakhir</p><p class="dpe-dashboard-detail__value">{{ $enrollment->ends_on?->format('d M Y') ?: 'Masih berjalan' }}</p></div>
            </div>

            <form method="POST" action="{{ route('superadmin.student-enrollments.destroy', $enrollment) }}" class="dpe-dashboard-form-actions">
                @csrf
                @method('DELETE')
                <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit"><x-dashboard.icon name="warning" /><span>Hapus pendaftaran</span></button>
            </form>
        </div>
    </article>
@endsection
