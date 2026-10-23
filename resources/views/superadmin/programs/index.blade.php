@extends('layouts.dashboard')

@section('title', 'Program Belajar')
@section('heading', 'Program Belajar')
@section('description', 'Kelola jenis program yang digunakan untuk pendaftaran, kelompok kelas, dan materi belajar.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Program</p>
            <h2>Program belajar</h2>
            <p>Atur program aktif, tipe kelas, dan target video bulanan yang menjadi acuan operasional.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.programs.create') }}">
                <x-dashboard.icon name="book" />
                <span>Tambah program</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar program</h2>
                <p>{{ $programs->total() }} program tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="book" /> Katalog operasional</span>
        </header>

        @if ($programs->count() === 0)
            <x-dashboard.empty
                title="Belum ada program belajar"
                message="Tambahkan program pertama agar dapat digunakan pada pendaftaran siswa dan kelompok kelas."
                icon="book"
                :action-url="route('superadmin.programs.create')"
                action-label="Tambah program"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Program</th>
                            <th>Tipe kelas</th>
                            <th>Target video</th>
                            <th>Penggunaan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($programs as $program)
                            <tr>
                                <td>
                                    <a href="{{ route('superadmin.programs.show', $program) }}"><strong>{{ $program->code }}</strong></a>
                                </td>
                                <td>
                                    <a href="{{ route('superadmin.programs.show', $program) }}"><strong>{{ $program->name }}</strong></a>
                                </td>
                                <td>{{ $program->class_type }}</td>
                                <td>{{ $program->monthly_video_target_override !== null ? $program->monthly_video_target_override . ' video / bulan' : 'Default sistem' }}</td>
                                <td>
                                    <span>{{ $program->enrollments_count }} pendaftaran</span><br>
                                    <span>{{ $program->class_groups_count }} kelompok</span>
                                </td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $program->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">
                                        {{ $program->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.programs.edit', $program) }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$programs" />
        @endif
    </article>
@endsection
