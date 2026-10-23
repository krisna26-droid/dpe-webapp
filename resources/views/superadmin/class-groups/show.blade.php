@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@php
    $teacherName = $classGroup->defaultTeacher?->user?->full_name
        ?: ($classGroup->defaultTeacher?->user?->username ?: 'Belum ditentukan');
    $activeMemberships = $classGroup->memberships->filter(fn ($membership) => $membership->isActiveOn());
@endphp

@extends('layouts.dashboard')

@section('title', $classGroup->name)
@section('heading', 'Detail Kelompok Kelas')
@section('description', 'Periksa konfigurasi kelompok dan peserta yang terhubung.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Kelas</p>
            <h2>{{ $classGroup->name }}</h2>
            <p>{{ $classGroup->code }} · {{ $classGroup->branch?->name ?: 'Cabang belum tersedia' }} · {{ $classGroup->program?->name ?: 'Program belum tersedia' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($operationRoutePrefix.'.class-group-memberships.create', ['class_group_id' => $classGroup->id]) }}">
                <x-dashboard.icon name="student" />
                <span>Tambah anggota</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.class-groups.edit', $classGroup) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit kelompok</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.class-groups.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--two">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Ringkasan kelompok</h2>
                    <p>Referensi utama untuk peserta, Teacher, dan sesi belajar.</p>
                </div>
                <span class="dpe-dashboard-badge {{ $classGroup->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">
                    {{ $classGroup->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Kode kelompok</p>
                        <p class="dpe-dashboard-detail__value">{{ $classGroup->code }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Nama kelompok</p>
                        <p class="dpe-dashboard-detail__value">{{ $classGroup->name }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Program belajar</p>
                        <p class="dpe-dashboard-detail__value">{{ $classGroup->program?->name ?: 'Belum tersedia' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Cabang</p>
                        <p class="dpe-dashboard-detail__value">{{ $classGroup->branch?->name ?: 'Belum tersedia' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Teacher utama</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $teacherName }}</p>
                    </div>
                </div>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Status dan peserta</h2>
                    <p>Ringkasan keanggotaan kelompok saat ini.</p>
                </div>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Total peserta</p>
                        <p class="dpe-dashboard-detail__value">{{ $classGroup->memberships->count() }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Peserta aktif hari ini</p>
                        <p class="dpe-dashboard-detail__value">{{ $activeMemberships->count() }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route($operationRoutePrefix.'.class-groups.status', $classGroup) }}" class="dpe-dashboard-form-actions">
                    @csrf
                    @method('PATCH')
                    @if(isset($recordVersion))<input type="hidden" name="record_version" value="{{ $recordVersion }}">@endif
                    @error('record_version')<p class="dpe-dashboard-field__error" role="alert">{{ $message }}</p>@enderror
                    <button class="dpe-dashboard-button {{ $classGroup->is_active ? 'dpe-dashboard-button--soft' : 'dpe-dashboard-button--primary' }}" type="submit">
                        <x-dashboard.icon name="refresh" />
                        <span>{{ $classGroup->is_active ? 'Nonaktifkan kelompok' : 'Aktifkan kelompok' }}</span>
                    </button>
                </form>
            </div>
        </article>
    </section>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Peserta kelompok</h2>
                <p>{{ $classGroup->memberships->count() }} riwayat peserta · {{ $activeMemberships->count() }} aktif hari ini</p>
            </div>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.class-group-memberships.create', ['class_group_id' => $classGroup->id]) }}">
                Tambah anggota
            </a>
        </header>

        @if ($classGroup->memberships->isEmpty())
            <x-dashboard.empty
                title="Belum ada peserta"
                message="Tambahkan siswa untuk menghubungkan mereka dengan kelompok ini."
                action-url="{{ route($operationRoutePrefix.'.class-group-memberships.create', ['class_group_id' => $classGroup->id]) }}"
                action-label="Tambah anggota"
                icon="student"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classGroup->memberships as $membership)
                            @php($membershipActive = $membership->isActiveOn())
                            <tr>
                                <td>
                                    <a href="{{ route($operationRoutePrefix.'.class-group-memberships.show', $membership) }}">
                                        <strong>{{ $membership->student?->full_name ?: 'Siswa tidak ditemukan' }}</strong>
                                    </a>
                                    @if ($membership->student?->portalUser?->username)<br><span>{{ $membership->student->portalUser->username }}</span>@endif
                                </td>
                                <td>{{ $membership->starts_on?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $membership->ends_on?->format('d M Y') ?: 'Berjalan' }}</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $membershipActive ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
                                        {{ $membershipActive ? 'Aktif' : 'Selesai' }}
                                    </span>
                                </td>
                                <td>
                                    <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.class-group-memberships.show', $membership) }}">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>
@endsection
