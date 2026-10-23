@php
    $teacherName = $assignment->teacher?->user?->full_name ?: ($assignment->teacher?->user?->username ?: 'Guru');
    $active = $assignment->isActiveOn();
@endphp

@extends('layouts.dashboard')

@section('title', 'Detail Penugasan Guru')
@section('heading', 'Detail Penugasan Guru')
@section('description', 'Periksa cabang dan periode penugasan guru.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Penugasan</p>
            <h2>{{ $teacherName }}</h2>
            <p>{{ $assignment->branch?->name ?: 'Cabang belum tersedia' }} · {{ $assignment->starts_on?->format('d M Y') ?: 'Tanggal belum tersedia' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.teacher-branch-assignments.edit', $assignment) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit penugasan</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.teacher-branch-assignments.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Ringkasan penugasan</h2>
                <p>Data ini dipakai sebagai referensi operasional guru di cabang.</p>
            </div>
            <span class="dpe-dashboard-badge {{ $active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
                {{ $active ? 'Aktif' : ($assignment->starts_on->isFuture() ? 'Belum dimulai' : 'Selesai') }}
            </span>
        </header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Guru</p>
                    <p class="dpe-dashboard-detail__value">{{ $teacherName }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">WhatsApp</p>
                    <p class="dpe-dashboard-detail__value">{{ $assignment->teacher?->whatsapp_number ?: 'Belum diisi' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Cabang</p>
                    <p class="dpe-dashboard-detail__value">{{ $assignment->branch?->name ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Kode cabang</p>
                    <p class="dpe-dashboard-detail__value">{{ $assignment->branch?->code ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Tanggal mulai</p>
                    <p class="dpe-dashboard-detail__value">{{ $assignment->starts_on?->format('d M Y') ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Tanggal berakhir</p>
                    <p class="dpe-dashboard-detail__value">{{ $assignment->ends_on?->format('d M Y') ?: 'Masih berjalan' }}</p>
                </div>
            </div>

            <form method="POST" action="{{ route('superadmin.teacher-branch-assignments.destroy', $assignment) }}" class="dpe-dashboard-form-actions">
                @csrf
                @method('DELETE')
                <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit">
                    <x-dashboard.icon name="warning" />
                    <span>Hapus penugasan</span>
                </button>
            </form>
        </div>
    </article>
@endsection
