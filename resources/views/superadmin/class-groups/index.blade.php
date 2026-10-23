@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@extends('layouts.dashboard')

@section('title', 'Kelompok Kelas')
@section('heading', 'Kelompok Kelas')
@section('description', $operationRoutePrefix === 'admin' ? 'Kelola kelas, Teacher, dan peserta pada cabang Anda.' : 'Kelola kelas, program, Teacher, dan peserta pada seluruh cabang.')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/class-group-filters.css') }}?v={{ filemtime(public_path('assets/dpe/class-group-filters.css')) }}">
@endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Kelas</p>
            <h2>Kelompok kelas</h2>
            <p>Hubungkan program belajar dengan cabang, Teacher, dan daftar peserta yang aktif.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($operationRoutePrefix.'.class-groups.create') }}">
                <x-dashboard.icon name="users" />
                <span>Tambah kelompok</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.class-group-memberships.index') }}">
                <x-dashboard.icon name="student" />
                <span>Kelola anggota</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    @include('operations._class-group-filters')

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar kelompok kelas</h2>
                <p>{{ $classGroups->total() }} kelompok {{ $hasClassGroupFilters ? 'sesuai filter' : 'tercatat' }}</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="users" /> Data operasional</span>
        </header>

        @if ($classGroups->count() === 0 && $hasClassGroupFilters)
            <x-dashboard.empty
                title="Tidak ada kelompok yang sesuai"
                message="Ubah pilihan filter atau gunakan Reset untuk melihat seluruh kelompok dalam cakupan Anda."
                action-url="{{ route($operationRoutePrefix.'.class-groups.index') }}"
                action-label="Reset filter"
                icon="users"
            />
        @elseif ($classGroups->count() === 0)
            <x-dashboard.empty
                title="Belum ada kelompok kelas"
                message="Buat kelompok untuk menghubungkan program, cabang, Teacher, dan peserta belajar."
                action-url="{{ route($operationRoutePrefix.'.class-groups.create') }}"
                action-label="Tambah kelompok"
                icon="users"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Kelompok</th>
                            <th>Program</th>
                            <th>Cabang</th>
                            <th>Teacher</th>
                            <th>Peserta</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($classGroups as $classGroup)
                            @php
                                $teacherName = $classGroup->defaultTeacher?->user?->full_name
                                    ?: ($classGroup->defaultTeacher?->user?->username ?: 'Belum ditentukan');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route($operationRoutePrefix.'.class-groups.show', $classGroup) }}">
                                        <strong>{{ $classGroup->code }}</strong>
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route($operationRoutePrefix.'.class-groups.show', $classGroup) }}">
                                        <strong>{{ $classGroup->name }}</strong>
                                    </a>
                                </td>
                                <td>
                                    <strong>{{ $classGroup->program?->name ?: '—' }}</strong>
                                    @if ($classGroup->program?->code)<br><span>{{ $classGroup->program->code }}</span>@endif
                                </td>
                                <td>
                                    <strong>{{ $classGroup->branch?->name ?: '—' }}</strong>
                                    @if ($classGroup->branch?->code)<br><span>{{ $classGroup->branch->code }}</span>@endif
                                </td>
                                <td>{{ $teacherName }}</td>
                                <td>{{ $classGroup->memberships_count }} peserta</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $classGroup->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">
                                        {{ $classGroup->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td>
                                    <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.class-groups.show', $classGroup) }}">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$classGroups" />
        @endif
    </article>
@endsection
