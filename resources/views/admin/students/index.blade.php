@extends('layouts.dashboard')

@section('title', 'Data Siswa')
@section('heading', 'Data Siswa')
@section('description', 'Kelola profil siswa yang berada pada cabang Anda.')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/teaching-directory.css') }}?v={{ filemtime(public_path('assets/dpe/teaching-directory.css')) }}">
@endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional Cabang / Siswa</p>
            <h2>Data siswa</h2>
            <p>Periksa profil, akun portal, program, dan status belajar siswa di cabang yang Anda kelola.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('admin.students.create') }}">
                <x-dashboard.icon name="plus" />
                <span>Tambah siswa</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar siswa cabang</h2>
                <p>{{ $students->total() }} siswa terhubung ke cabang aktif Anda</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="student" /> Data cabang</span>
        </header>

        @if ($students->count() === 0)
            <x-dashboard.empty
                title="Belum ada siswa di cabang ini"
                message="Siswa akan tampil setelah profilnya memiliki pendaftaran aktif pada cabang yang Anda kelola."
                icon="student"
                :action-url="route('admin.students.create')"
                action-label="Tambah profil siswa"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Akun portal</th>
                            <th>Program / cabang</th>
                            <th>Teacher pengajar</th>
                            <th>Mulai belajar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php
                                $activeEnrollment = $student->enrollments->first(fn ($item) => $item->isActiveOn());
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.students.show', $student) }}">
                                        <strong>{{ $student->full_name }}</strong>
                                    </a>
                                    @if ($student->school_name || $student->grade_name)
                                        <br><span>{{ collect([$student->school_name, $student->grade_name])->filter()->join(' · ') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <strong>{{ $student->portalUser?->username ?: '—' }}</strong>
                                    @if ($student->portalUser?->email)<br><span>{{ $student->portalUser->email }}</span>@endif
                                </td>
                                <td>
                                    @if ($activeEnrollment)
                                        <strong>{{ $activeEnrollment->program?->name ?: 'Program' }}</strong><br>
                                        <span>{{ $activeEnrollment->branch?->name ?: 'Cabang belum tersedia' }}</span>
                                    @else
                                        <span>Belum terdaftar</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="dpe-teaching-people">
                                        @if ($studentTeachers->has($student->id))
                                            <ul class="dpe-teaching-names">
                                                @foreach ($studentTeachers->get($student->id) as $assignedTeacher)
                                                    <li>{{ $assignedTeacher->teacher_name ?: $assignedTeacher->teacher_username }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span>Belum ada pengajar aktif</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $student->began_on?->format('d M Y') ?: '—' }}</td>
                                <td>
                                    <x-dashboard.status :status="$student->status" :label="$student->status === 'active' ? 'Aktif' : ucfirst($student->status)" />
                                </td>
                                <td>
                                    <div class="dpe-dashboard-table-actions">
                                        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('admin.students.show', $student) }}">Detail</a>
                                        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('admin.students.edit', $student) }}">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$students" />
        @endif
    </article>
@endsection
