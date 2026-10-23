@php
    $classType = trim((string) $program->class_type);
@endphp

@extends('layouts.dashboard')

@section('title', $program->name)
@section('heading', 'Detail Program Belajar')
@section('description', 'Periksa pengaturan dan penggunaan program belajar.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Program</p>
            <h2>{{ $program->name }}</h2>
            <p>{{ $program->code }} · {{ $classType ?: 'Tipe kelas belum diisi' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.programs.edit', $program) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit program</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.programs.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--two">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Informasi program</h2>
                    <p>Konfigurasi dasar yang dipakai oleh modul pembelajaran.</p>
                </div>
                <span class="dpe-dashboard-badge {{ $program->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">
                    {{ $program->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Kode program</p>
                        <p class="dpe-dashboard-detail__value">{{ $program->code }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Nama program</p>
                        <p class="dpe-dashboard-detail__value">{{ $program->name }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Tipe kelas</p>
                        <p class="dpe-dashboard-detail__value">{{ $classType ?: 'Belum diisi' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Target video bulanan</p>
                        <p class="dpe-dashboard-detail__value">{{ $program->monthly_video_target_override !== null ? $program->monthly_video_target_override . ' video' : 'Mengikuti default sistem' }}</p>
                    </div>
                </div>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Penggunaan program</h2>
                    <p>Ringkasan relasi operasional yang sudah memakai program ini.</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="report" /> Ringkasan</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-grid dpe-dashboard-grid--two">
                    <div class="dpe-dashboard-metric">
                        <div class="dpe-dashboard-metric__icon"><x-dashboard.icon name="student" /></div>
                        <div class="dpe-dashboard-metric__body">
                            <span class="dpe-dashboard-metric__label">Pendaftaran siswa</span>
                            <strong class="dpe-dashboard-metric__value">{{ $program->enrollments_count }}</strong>
                        </div>
                    </div>
                    <div class="dpe-dashboard-metric">
                        <div class="dpe-dashboard-metric__icon"><x-dashboard.icon name="users" /></div>
                        <div class="dpe-dashboard-metric__body">
                            <span class="dpe-dashboard-metric__label">Kelompok kelas</span>
                            <strong class="dpe-dashboard-metric__value">{{ $program->class_groups_count }}</strong>
                        </div>
                    </div>
                </div>
                <p class="dpe-dashboard-field__hint">Menonaktifkan program mempertahankan riwayat yang sudah ada, tetapi program tidak lagi disarankan untuk data baru.</p>
                <form method="POST" action="{{ route('superadmin.programs.status', $program) }}" class="dpe-dashboard-form-actions">
                    @csrf
                    @method('PATCH')
                    <button class="dpe-dashboard-button {{ $program->is_active ? 'dpe-dashboard-button--soft' : 'dpe-dashboard-button--primary' }}" type="submit">
                        <x-dashboard.icon name="refresh" />
                        <span>{{ $program->is_active ? 'Nonaktifkan program' : 'Aktifkan program' }}</span>
                    </button>
                </form>
            </div>
        </article>
    </section>
@endsection
