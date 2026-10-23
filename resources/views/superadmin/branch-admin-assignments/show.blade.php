@php
    $adminName = $assignment->admin?->full_name ?: ($assignment->admin?->username ?: 'Admin cabang');
    $active = $assignment->starts_on?->lte(now()->startOfDay()) && ($assignment->ends_on === null || $assignment->ends_on->gte(now()->startOfDay()));
@endphp

@extends('layouts.dashboard')

@section('title', 'Detail Penugasan Admin Cabang')
@section('heading', 'Detail Admin Cabang')
@section('description', 'Periksa cabang dan periode tanggung jawab admin.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Penugasan</p>
            <h2>{{ $adminName }}</h2>
            <p>{{ $assignment->branch?->name ?: 'Cabang belum tersedia' }} · {{ $assignment->starts_on?->format('d M Y') ?: 'Tanggal belum tersedia' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.branch-admin-assignments.edit', $assignment) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit penugasan</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.branch-admin-assignments.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Ringkasan penugasan</h2>
                <p>Data ini menentukan akses operasional admin terhadap cabang.</p>
            </div>
            <span class="dpe-dashboard-badge {{ $active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
                {{ $active ? 'Aktif' : 'Selesai' }}
            </span>
        </header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Admin</p>
                    <p class="dpe-dashboard-detail__value">{{ $adminName }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Username</p>
                    <p class="dpe-dashboard-detail__value">{{ $assignment->admin?->username ?: '—' }}</p>
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

            <form method="POST" action="{{ route('superadmin.branch-admin-assignments.destroy', $assignment) }}" class="dpe-dashboard-form-actions">
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
