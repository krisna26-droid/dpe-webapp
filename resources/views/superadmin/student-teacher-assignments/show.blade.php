@extends('layouts.dashboard')
@section('title', 'Detail Penugasan Siswa')
@section('heading', 'Siswa & Pendaftaran')
@section('description', 'Periksa siswa, Teacher, dan tanggal berlaku penugasan.')
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Operasional / Penugasan siswa</p><h2>{{ $assignment->student?->full_name ?: 'Siswa' }}</h2><p>{{ $assignment->teacher?->user?->full_name ?: 'Teacher belum tersedia' }}</p></div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.student-teacher-assignments.edit', $assignment) }}"><x-dashboard.icon name="settings" /> Edit penugasan</a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.student-teacher-assignments.index') }}"><x-dashboard.icon name="back" /> Kembali ke Penugasan Siswa</a>
        </div>
    </section>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Periode penugasan</h2><p>Teacher mengikuti tanggal penugasan dan tanggal mulai les siswa.</p></div></header>
        <div class="dpe-dashboard-card__body"><div class="dpe-dashboard-detail-grid">
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Siswa</p><p class="dpe-dashboard-detail__value">{{ $assignment->student?->full_name ?: '—' }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Teacher</p><p class="dpe-dashboard-detail__value">{{ $assignment->teacher?->user?->full_name ?: '—' }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Mulai</p><p class="dpe-dashboard-detail__value">{{ $assignment->starts_on?->format('d M Y') ?: '—' }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Berakhir</p><p class="dpe-dashboard-detail__value">{{ $assignment->ends_on?->format('d M Y') ?: 'Berjalan' }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Prioritas laporan</p><p class="dpe-dashboard-detail__value">{{ $assignment->is_report_owner ? 'Pemilik laporan' : 'Teacher pengajar' }}</p></div>
        </div></div>
    </article>
@endsection
