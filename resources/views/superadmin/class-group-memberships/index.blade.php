@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@extends('layouts.dashboard')

@section('title', 'Peserta Kelompok')
@section('heading', 'Kelompok Kelas')
@section('description', 'Kelola riwayat siswa yang terhubung dengan setiap kelompok kelas.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Kelas</p>
            <h2>Peserta kelompok</h2>
            <p>Periode mulai dan berakhir menjaga riwayat perpindahan siswa tetap dapat ditelusuri.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($operationRoutePrefix.'.class-group-memberships.create') }}">
                <x-dashboard.icon name="student" />
                <span>Tambah anggota</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.class-groups.index') }}">
                <x-dashboard.icon name="users" />
                <span>Kelompok kelas</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    @include('operations._branch-filters')

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar peserta</h2>
                <p>{{ $memberships->total() }} riwayat keanggotaan tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="student" /> Peserta kelas</span>
        </header>

        @if ($memberships->count() === 0)
            <x-dashboard.empty
                title="Belum ada peserta kelompok"
                message="Tambahkan siswa ke kelompok agar mereka dapat dipakai sebagai referensi sesi belajar."
                action-url="{{ route($operationRoutePrefix.'.class-group-memberships.create') }}"
                action-label="Tambah anggota"
                icon="student"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Kelompok</th>
                            <th>Program / Cabang</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($memberships as $membership)
                            @php($active = $membership->isActiveOn())
                            <tr>
                                <td>
                                    <a href="{{ route($operationRoutePrefix.'.class-group-memberships.show', $membership) }}">
                                        <strong>{{ $membership->student?->full_name ?: 'Siswa tidak ditemukan' }}</strong>
                                    </a>
                                    @if ($membership->student?->school_name || $membership->student?->grade_name)
                                        <br><span>{{ collect([$membership->student->school_name, $membership->student->grade_name])->filter()->join(' · ') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route($operationRoutePrefix.'.class-groups.show', $membership->classGroup) }}">
                                        <strong>{{ $membership->classGroup?->name ?: 'Kelompok tidak ditemukan' }}</strong>
                                    </a>
                                    @if ($membership->classGroup?->code)<br><span>{{ $membership->classGroup->code }}</span>@endif
                                </td>
                                <td>
                                    <strong>{{ $membership->classGroup?->program?->name ?: '—' }}</strong>
                                    @if ($membership->classGroup?->branch?->name)<br><span>{{ $membership->classGroup->branch->name }}</span>@endif
                                </td>
                                <td>{{ $membership->starts_on?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $membership->ends_on?->format('d M Y') ?: 'Berjalan' }}</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
                                        {{ $active ? 'Aktif' : 'Selesai' }}
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
            <x-dashboard.pagination :paginator="$memberships" />
        @endif
    </article>
@endsection
