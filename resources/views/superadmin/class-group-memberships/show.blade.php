@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@php
    $active = $classGroupMembership->isActiveOn();
    $classGroup = $classGroupMembership->classGroup;
    $student = $classGroupMembership->student;
@endphp

@extends('layouts.dashboard')

@section('title', 'Detail Anggota Kelompok')
@section('heading', 'Kelompok Kelas')
@section('description', 'Periksa hubungan siswa dengan kelompok dan periode belajarnya.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Kelas</p>
            <h2>{{ $student?->full_name ?: 'Siswa tidak ditemukan' }}</h2>
            <p>{{ $classGroup?->name ?: 'Kelompok tidak ditemukan' }} · {{ $classGroup?->branch?->name ?: 'Cabang belum tersedia' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($operationRoutePrefix.'.class-group-memberships.edit', $classGroupMembership) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit anggota</span>
            </a>
            @if ($classGroup)
                <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.class-groups.show', $classGroup) }}">
                    <x-dashboard.icon name="users" />
                    <span>Lihat kelompok</span>
                </a>
            @endif
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.class-group-memberships.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Ringkasan keanggotaan</h2>
                <p>Periode ini membedakan riwayat belajar lama dan keanggotaan yang masih aktif.</p>
            </div>
            <span class="dpe-dashboard-badge {{ $active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
                {{ $active ? 'Aktif' : 'Selesai' }}
            </span>
        </header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Siswa</p>
                    <p class="dpe-dashboard-detail__value">{{ $student?->full_name ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Status siswa</p>
                    <p class="dpe-dashboard-detail__value">{{ $student?->status === 'active' ? 'Aktif' : ucfirst($student?->status ?: '—') }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Kelompok</p>
                    <p class="dpe-dashboard-detail__value">{{ $classGroup?->name ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Kode kelompok</p>
                    <p class="dpe-dashboard-detail__value">{{ $classGroup?->code ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Program</p>
                    <p class="dpe-dashboard-detail__value">{{ $classGroup?->program?->name ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Cabang</p>
                    <p class="dpe-dashboard-detail__value">{{ $classGroup?->branch?->name ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Tanggal mulai</p>
                    <p class="dpe-dashboard-detail__value">{{ $classGroupMembership->starts_on?->format('d M Y') ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Tanggal berakhir</p>
                    <p class="dpe-dashboard-detail__value">{{ $classGroupMembership->ends_on?->format('d M Y') ?: 'Masih berjalan' }}</p>
                </div>
            </div>
        </div>
    </article>
<x-dashboard.student-session-summary :student="$classGroupMembership->student" />
@endsection
