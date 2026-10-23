@php
    $statusKey = ucfirst(strtolower(trim($slot->availability_status)));
    $statusLabel = \App\Services\TeacherWorkspaceService::SLOT_STATUSES[$statusKey] ?? $slot->availability_status;
@endphp
@extends('layouts.dashboard')
@section('title', 'Detail Slot Ketersediaan')
@section('heading', 'Detail Slot Ketersediaan')
@section('description', 'Ringkasan slot waktu dan sesi yang sudah dijadwalkan.')
@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Teacher / Slot Ketersediaan</p><h2>{{ $slot->branch?->name }}</h2><p>{{ $slot->available_on->format('d M Y') }} · {{ substr($slot->starts_at,0,5) }}–{{ substr($slot->ends_at,0,5) }}</p></div><div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('teacher.availability.edit', $slot) }}">Edit slot</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.availability.index', ['from'=>$slot->available_on->copy()->startOfMonth()->toDateString(),'to'=>$slot->available_on->copy()->endOfMonth()->toDateString()]) }}"><x-dashboard.icon name="back" />Kembali ke slot saya</a></div></section>
    <div class="dpe-teacher-stack">
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Ringkasan slot</h2><p>Referensi ketersediaan untuk penyusunan jadwal oleh Admin.</p></div><span class="dpe-dashboard-badge {{ $statusKey === 'Available' ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $statusLabel }}</span></header><div class="dpe-dashboard-card__body"><div class="dpe-dashboard-detail-grid">
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Cabang</p><p class="dpe-dashboard-detail__value">{{ $slot->branch?->name }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Tanggal</p><p class="dpe-dashboard-detail__value">{{ $slot->available_on->format('d M Y') }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Waktu</p><p class="dpe-dashboard-detail__value">{{ substr($slot->starts_at,0,5) }}–{{ substr($slot->ends_at,0,5) }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Zona waktu cabang</p><p class="dpe-dashboard-detail__value">{{ $slot->branch?->timezone_name ?: config('app.timezone') }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Jenis kelas</p><p class="dpe-dashboard-detail__value">{{ ucfirst(strtolower($slot->class_type)) }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Status</p><p class="dpe-dashboard-detail__value">{{ $statusLabel }}</p></div>
            <div class="dpe-dashboard-detail dpe-dashboard-detail--full"><p class="dpe-dashboard-detail__label">Catatan</p><p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $slot->notes ?: 'Tidak ada catatan.' }}</p></div>
        </div></div></article>
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Sesi pada waktu ini</h2><p>Mengubah slot tidak memindahkan atau membatalkan sesi. Hubungi Admin untuk perubahan jadwal.</p></div><span class="dpe-dashboard-badge">{{ $relatedSessions->count() }} sesi</span></header>
            @if($relatedSessions->isEmpty())<x-dashboard.empty title="Belum ada sesi pada rentang slot ini" message="Admin dapat memakai slot tersedia saat menyiapkan jadwal mengajar Anda." icon="calendar" />
            @else<div class="dpe-dashboard-card__body"><ul class="dpe-teacher-related">@foreach($relatedSessions as $session)<li><a href="{{ route('teacher.schedule.show', $session) }}"><strong>{{ $session->classGroup?->name ?: 'Private / tanpa kelompok' }}</strong> · {{ $session->program?->name }} · {{ $session->planned_start_at->format('d M Y H:i') }}–{{ $session->planned_end_at->format('H:i') }}</a></li>@endforeach</ul></div>@endif
        </article>
    </div>
@endsection
