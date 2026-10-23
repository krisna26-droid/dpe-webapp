@extends('layouts.dashboard')

@section('title', 'Detail Pengguna Siswa')
@section('heading', 'Pengguna')
@section('description', 'Akun portal dan pendaftaran siswa pada cabang yang Anda kelola.')

@section('content')
    @php($student = $managedUser->studentProfile)
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional Cabang / Akses</p>
            <h2>{{ $managedUser->full_name }}</h2><p>Akun Siswa / Parent</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            @can('view', $student)
                <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('admin.students.show', $student) }}">Lihat profil siswa</a>
            @endcan
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.users.index') }}">Kembali</a>
        </div>
    </section>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Akun portal</h2><p>Akun, profil dan pendaftaran cabang sudah terhubung.</p></div></header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Nama siswa</p><p class="dpe-dashboard-detail__value">{{ $student->full_name }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Username</p><p class="dpe-dashboard-detail__value">{{ $managedUser->username }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Email</p><p class="dpe-dashboard-detail__value">{{ $managedUser->email ?: '—' }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Status akun</p><p class="dpe-dashboard-detail__value">{{ $managedUser->is_active ? 'Aktif' : 'Nonaktif' }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Sekolah / kelas</p><p class="dpe-dashboard-detail__value">{{ $student->school_name ?: '—' }} / {{ $student->grade_name ?: '—' }}</p></div>
            </div>
        </div>
    </article>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Pendaftaran cabang</h2><p>Program dan tanggal mulai belajar siswa.</p></div></header>
        <div class="dpe-dashboard-table-wrap">
            <table class="dpe-dashboard-table">
                <thead><tr><th>Cabang</th><th>Program</th><th>Mulai</th><th>Status pendaftaran</th></tr></thead>
                <tbody>
                    @foreach ($student->enrollments as $enrollment)
                        <tr><td>{{ $enrollment->branch->name }}</td><td>{{ $enrollment->program->name }}</td><td>{{ $enrollment->starts_on->translatedFormat('d F Y') }}</td><td>{{ $enrollment->starts_on->isAfter(today()) ? 'Akan dimulai' : 'Berjalan' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </article>
@endsection
