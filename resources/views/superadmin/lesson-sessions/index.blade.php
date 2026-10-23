@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@extends('layouts.dashboard')

@section('title', 'Sesi Belajar')
@section('heading', $operationRoutePrefix === 'admin' ? 'Jadwal & Sesi' : 'Sesi Belajar')
@section('description', $operationRoutePrefix === 'admin' ? 'Kelola jadwal dan sesi belajar pada cabang Anda.' : 'Pantau jadwal sesi belajar yang tercatat di seluruh cabang.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Jadwal</p>
            <h2>Sesi belajar</h2>
            <p>Daftar agenda belajar, guru, program, dan status sesi yang terjadwal.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($operationRoutePrefix.'.lesson-sessions.create') }}">
                <x-dashboard.icon name="calendar" />
                <span>Tambah sesi</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    @include('operations._branch-filters')

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar sesi</h2>
                <p>{{ $lessonSessions->total() }} sesi tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="calendar" /> Jadwal belajar</span>
        </header>

        @if ($lessonSessions->count() === 0)
            <x-dashboard.empty
                title="Belum ada sesi belajar"
                message="Sesi belajar akan tampil setelah jadwal dibuat dan tersimpan di sistem."
                icon="calendar"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Cabang</th>
                            <th>Guru</th>
                            <th>Program / Kelas</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lessonSessions as $lessonSession)
                            @php
                                $statusLabel = ucwords(str_replace(['_', '-'], ' ', (string) $lessonSession->status));
                                $statusClass = in_array(strtolower((string) $lessonSession->status), ['completed', 'complete', 'done', 'selesai'], true)
                                    ? 'dpe-dashboard-badge--success'
                                    : (in_array(strtolower((string) $lessonSession->status), ['cancelled', 'canceled', 'dibatalkan'], true) ? 'dpe-dashboard-badge--danger' : 'dpe-dashboard-badge--warning');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route($operationRoutePrefix.'.lesson-sessions.show', $lessonSession) }}">
                                        <strong>{{ $lessonSession->planned_start_at?->format('d M Y') ?? '—' }}</strong>
                                    </a><br>
                                    <span>{{ $lessonSession->planned_start_at?->format('H:i') ?? '—' }}–{{ $lessonSession->planned_end_at?->format('H:i') ?? '—' }}</span>
                                </td>
                                <td>{{ $lessonSession->branch?->name ?: '—' }}</td>
                                <td>{{ $lessonSession->teacher?->user?->full_name ?: ($lessonSession->teacher?->user?->username ?: '—') }}</td>
                                <td>
                                    <strong>{{ $lessonSession->program?->name ?: '—' }}</strong><br>
                                    <span>{{ $lessonSession->classGroup?->name ?: 'Tanpa kelompok' }}</span>
                                </td>
                                <td><span class="dpe-dashboard-badge {{ $statusClass }}">{{ $statusLabel ?: 'Belum ditentukan' }}</span></td>
                                <td>
                                    <div class="dpe-dashboard-table-actions">
                                    <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.student-attendance.show', $lessonSession) }}">Absensi</a>
                                    <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.lesson-sessions.edit', $lessonSession) }}">Edit</a>
                                    @if($lessonSession->schedule_series_id)<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.lesson-series.show', $lessonSession->schedule_series_id) }}">Seri jadwal</a>@endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$lessonSessions" />
        @endif
    </article>
@endsection
