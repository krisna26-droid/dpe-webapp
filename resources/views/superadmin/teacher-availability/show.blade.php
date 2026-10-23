@php
    $teacherName = $availability->teacher?->user?->full_name ?: ($availability->teacher?->user?->username ?: 'Guru');
    $status = strtolower((string) $availability->availability_status);
    $statusClass = in_array($status, ['available', 'tersedia', 'open', 'aktif'], true)
        ? 'dpe-dashboard-badge--success'
        : (in_array($status, ['blocked', 'blocked_out', 'unavailable', 'nonaktif'], true) ? 'dpe-dashboard-badge--danger' : 'dpe-dashboard-badge--warning');
@endphp

@extends('layouts.dashboard')

@section('title', 'Detail Ketersediaan Guru')
@section('heading', 'Detail Ketersediaan Guru')
@section('description', 'Periksa slot waktu mengajar dan statusnya.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Jadwal</p>
            <h2>{{ $teacherName }}</h2>
            <p>{{ $availability->available_on?->format('d M Y') ?: 'Tanggal belum tersedia' }} · {{ substr((string) $availability->starts_at, 0, 5) }}–{{ substr((string) $availability->ends_at, 0, 5) }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.teacher-availability.edit', $availability) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit slot</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.teacher-availability.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Ringkasan slot</h2>
                <p>Slot ini menjadi referensi penyusunan sesi belajar.</p>
            </div>
            <span class="dpe-dashboard-badge {{ $statusClass }}">{{ $availability->availability_status ?: 'Belum ditentukan' }}</span>
        </header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Guru</p>
                    <p class="dpe-dashboard-detail__value">{{ $teacherName }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">WhatsApp</p>
                    <p class="dpe-dashboard-detail__value">{{ $availability->teacher?->whatsapp_number ?: 'Belum diisi' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Cabang</p>
                    <p class="dpe-dashboard-detail__value">{{ $availability->branch?->name ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Tanggal tersedia</p>
                    <p class="dpe-dashboard-detail__value">{{ $availability->available_on?->format('d M Y') ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Rentang waktu</p>
                    <p class="dpe-dashboard-detail__value">{{ substr((string) $availability->starts_at, 0, 5) }}–{{ substr((string) $availability->ends_at, 0, 5) }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Jenis kelas</p>
                    <p class="dpe-dashboard-detail__value">{{ $availability->class_type ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                    <p class="dpe-dashboard-detail__label">Catatan</p>
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $availability->notes ?: 'Tidak ada catatan.' }}</p>
                </div>
            </div>
        </div>
    </article>
@endsection
