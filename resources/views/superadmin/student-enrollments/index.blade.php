@extends('layouts.dashboard')

@section('title', 'Pendaftaran Siswa')
@section('heading', 'Pendaftaran Siswa')
@section('description', 'Hubungkan siswa dengan program belajar, cabang, dan periode layanan.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Siswa / Pendaftaran</p>
            <h2>Program dan cabang siswa</h2>
            <p>Periode pendaftaran dipakai sebagai referensi jadwal, perpindahan cabang, dan laporan.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.student-enrollments.create') }}"><x-dashboard.icon name="book" /><span>Tambah pendaftaran</span></a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.students.index') }}"><x-dashboard.icon name="student" /><span>Profil siswa</span></a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.guardians.index') }}"><x-dashboard.icon name="users" /><span>Wali siswa</span></a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div><h2>Daftar pendaftaran</h2><p>{{ $enrollments->total() }} pendaftaran tercatat</p></div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="book" /> Operasional</span>
        </header>

        @if ($enrollments->count() === 0)
            <x-dashboard.empty title="Belum ada pendaftaran" message="Tambahkan program dan cabang untuk mengaktifkan perjalanan belajar siswa." action-url="{{ route('superadmin.student-enrollments.create') }}" action-label="Tambah pendaftaran" icon="book" />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead><tr><th>Siswa</th><th>Program</th><th>Cabang</th><th>Mulai</th><th>Berakhir</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($enrollments as $enrollment)
                            @php($active = $enrollment->isActiveOn())
                            <tr>
                                <td><a href="{{ route('superadmin.students.show', $enrollment->student) }}"><strong>{{ $enrollment->student?->full_name ?: 'Siswa tidak ditemukan' }}</strong></a>@if ($enrollment->student?->portalUser?->username)<br><span>{{ $enrollment->student->portalUser->username }}</span>@endif</td>
                                <td><strong>{{ $enrollment->program?->name ?: '—' }}</strong><br><span>{{ $enrollment->program?->code ?: '' }}</span></td>
                                <td>{{ $enrollment->branch?->name ?: '—' }}</td>
                                <td>{{ $enrollment->starts_on?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $enrollment->ends_on?->format('d M Y') ?: 'Berjalan' }}</td>
                                <td><span class="dpe-dashboard-badge {{ $active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $active ? 'Aktif' : 'Selesai' }}</span></td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-enrollments.show', $enrollment) }}">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$enrollments" />
        @endif
    </article>
@endsection
