@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@php
    $teacherName = $lessonSession->teacher?->user?->full_name ?: ($lessonSession->teacher?->user?->username ?: 'Guru');
    $status = strtolower((string) $lessonSession->status);
    $statusClass = in_array($status, ['completed', 'complete', 'done', 'selesai'], true)
        ? 'dpe-dashboard-badge--success'
        : (in_array($status, ['cancelled', 'canceled', 'dibatalkan'], true) ? 'dpe-dashboard-badge--danger' : 'dpe-dashboard-badge--warning');
@endphp

@extends('layouts.dashboard')

@section('title', 'Detail Sesi Belajar')
@section('heading', 'Detail Sesi Belajar')
@section('description', 'Periksa jadwal, status, dan catatan sesi belajar.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Jadwal</p>
            <h2>{{ $lessonSession->topic ?: 'Sesi belajar' }}</h2>
            <p>{{ $lessonSession->planned_start_at?->format('d M Y H:i') ?: 'Waktu belum tersedia' }} · {{ $lessonSession->branch?->name ?: 'Cabang belum tersedia' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            @if($lessonSession->schedule_series_id)<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.lesson-series.show', $lessonSession->schedule_series_id) }}">Lihat seri jadwal</a>@endif
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($operationRoutePrefix.'.lesson-sessions.edit', $lessonSession) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit sesi</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.lesson-sessions.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--two">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Ringkasan sesi</h2>
                    <p>Informasi utama jadwal dan penanggung jawab sesi.</p>
                </div>
                <span class="dpe-dashboard-badge {{ $statusClass }}">{{ ucwords(str_replace(['_', '-'], ' ', (string) $lessonSession->status)) }}</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Cabang</p>
                        <p class="dpe-dashboard-detail__value">{{ $lessonSession->branch?->name ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Guru</p>
                        <p class="dpe-dashboard-detail__value">{{ $teacherName }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Program</p>
                        <p class="dpe-dashboard-detail__value">{{ $lessonSession->program?->name ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Lokasi mengajar</p>
                        <p class="dpe-dashboard-detail__value">{{ $lessonSession->isAtStudentHome() ? 'Private di rumah siswa' : 'Di cabang' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Kelompok kelas</p>
                        <p class="dpe-dashboard-detail__value">{{ $lessonSession->classGroup?->name ?: 'Tanpa kelompok' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Mulai rencana</p>
                        <p class="dpe-dashboard-detail__value">{{ $lessonSession->planned_start_at?->format('d M Y H:i') ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Selesai rencana</p>
                        <p class="dpe-dashboard-detail__value">{{ $lessonSession->planned_end_at?->format('d M Y H:i') ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Mulai aktual</p>
                        <p class="dpe-dashboard-detail__value">{{ $lessonSession->actual_start_at?->format('d M Y H:i') ?: 'Belum dicatat' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Selesai aktual</p>
                        <p class="dpe-dashboard-detail__value">{{ $lessonSession->actual_end_at?->format('d M Y H:i') ?: 'Belum dicatat' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Penjadwalan ulang dari</p>
                        <p class="dpe-dashboard-detail__value">{{ $lessonSession->rescheduledFrom?->planned_start_at?->format('d M Y H:i') ?: 'Bukan sesi penjadwalan ulang' }}</p>
                    </div>
                </div>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Catatan pembelajaran</h2>
                    <p>Ringkasan yang dapat dilanjutkan oleh guru dan admin.</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="book" /> Catatan</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Topik</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $lessonSession->topic ?: 'Belum diisi' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Materi</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $lessonSession->material ?: 'Belum diisi' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Aktivitas</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $lessonSession->activity ?: 'Belum diisi' }}</p>
                    </div>
                </div>
            </div>
        </article>
    </section>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Catatan siswa tersimpan</h2>
                <p>{{ $lessonSession->sessionStudents->count() }} catatan peserta tersimpan pada sesi ini.</p>
            </div>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.student-attendance.show', $lessonSession) }}">Peserta / absensi</a>
        </header>
        @if ($lessonSession->sessionStudents->isEmpty())
            <x-dashboard.empty
                title="Belum ada catatan siswa tersimpan"
                message="Buka Peserta / absensi untuk melihat peserta kelas atau memilih peserta private dan mencatat kehadiran."
                icon="student"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Status kehadiran</th>
                            <th>Catatan individu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lessonSession->sessionStudents as $sessionStudent)
                            <tr>
                                <td><strong>{{ $sessionStudent->student?->full_name ?: 'Siswa' }}</strong></td>
                                <td>{{ \App\Services\StudentAttendanceService::STATUSES[$sessionStudent->attendance_status] ?? ($sessionStudent->attendance_status ?: 'Belum dicatat') }}</td>
                                <td>{{ $sessionStudent->individual_learning_note ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>
@endsection
