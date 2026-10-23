@extends('layouts.dashboard')

@section('title', 'Detail Guru')
@section('heading', 'Detail Guru')
@section('description', 'Periksa profil guru dan riwayat penugasan cabangnya.')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/teaching-directory.css') }}?v={{ filemtime(public_path('assets/dpe/teaching-directory.css')) }}">
@endpush

@section('content')
    @php
        $teacherName = $teacher->user?->full_name ?: ($teacher->user?->username ?: 'Guru tidak ditemukan');
    @endphp

    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional Cabang / Guru</p>
            <h2>{{ $teacherName }}</h2>
            <p>{{ $teacher->user?->email ?: 'Email belum diisi' }} · {{ $teacher->whatsapp_number ?: 'WhatsApp belum diisi' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            @if ($canEditTeacher)<a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('admin.teachers.edit', $teacher) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit profil</span>
            </a>@endif
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.teachers.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Ringkasan profil</h2>
                <p>Data kontak dan akun login guru.</p>
            </div>
            <x-dashboard.status :status="$teacher->is_active" :label="$teacher->is_active ? 'Aktif' : 'Nonaktif'" />
        </header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Nama guru</p>
                    <p class="dpe-dashboard-detail__value">{{ $teacherName }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Username portal</p>
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $teacher->user?->username ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Email</p>
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $teacher->user?->email ?: 'Belum diisi' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Nomor WhatsApp</p>
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $teacher->whatsapp_number ?: 'Belum diisi' }}</p>
                </div>
            </div>
        </div>
    </article>

    @include('admin.teachers._students')

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Penugasan cabang</h2>
                <p>{{ $teacher->branchAssignments->count() }} riwayat penugasan tercatat.</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="building" /> Penugasan</span>
        </header>
        @if ($teacher->branchAssignments->isEmpty())
            <x-dashboard.empty
                title="Belum ada penugasan cabang"
                message="Hubungi Super Admin untuk menghubungkan guru dengan cabang dan periode penugasan."
                icon="building"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead><tr><th>Cabang</th><th>Mulai</th><th>Berakhir</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($teacher->branchAssignments->sortByDesc('starts_on') as $assignment)
                            @php($assignmentActive = $assignment->starts_on?->lte(now()->startOfDay()) && ($assignment->ends_on === null || $assignment->ends_on->gte(now()->startOfDay())))
                            <tr>
                                <td><strong>{{ $assignment->branch?->name ?: 'Cabang tidak ditemukan' }}</strong></td>
                                <td>{{ $assignment->starts_on?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $assignment->ends_on?->format('d M Y') ?: 'Berjalan' }}</td>
                                <td><span class="dpe-dashboard-badge {{ $assignmentActive ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $assignmentActive ? 'Aktif' : 'Selesai / belum aktif' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>
@endsection
