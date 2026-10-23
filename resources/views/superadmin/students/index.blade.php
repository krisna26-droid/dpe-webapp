@extends('layouts.dashboard')

@section('title', 'Siswa & Pendaftaran')
@section('heading', 'Siswa & Pendaftaran')
@section('description', 'Kelola profil anak, akun portal, program, cabang, dan penerima rapor dari satu workspace.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Siswa</p>
            <h2>Profil siswa</h2>
            <p>Setiap anak memiliki profil dan akun portal sendiri, termasuk jika bersaudara.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-teacher-assignments.index') }}"><x-dashboard.icon name="teacher" /> Penugasan Teacher</a>
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.students.create') }}">
                <x-dashboard.icon name="student" />
                <span>Tambah siswa</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-enrollments.index') }}">
                <x-dashboard.icon name="book" />
                <span>Pendaftaran</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.guardians.index') }}">
                <x-dashboard.icon name="users" />
                <span>Wali siswa</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar profil siswa</h2>
                <p>{{ $students->total() }} siswa tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="student" /> Data pusat</span>
        </header>

        @if ($students->count() === 0)
            <x-dashboard.empty
                title="Belum ada profil siswa"
                message="Buat profil siswa setelah akun student tersedia di menu Pengguna."
                action-url="{{ route('superadmin.students.create') }}"
                action-label="Tambah profil siswa"
                icon="student"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Akun portal</th>
                            <th>Program / cabang aktif</th>
                            <th>Wali</th>
                            <th>Mulai belajar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php($activeEnrollment = $student->enrollments->first(fn ($item) => $item->isActiveOn()))
                            <tr>
                                <td>
                                    <a href="{{ route('superadmin.students.show', $student) }}">
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
                                <td>{{ $student->guardians->count() }} kontak</td>
                                <td>{{ $student->began_on?->format('d M Y') ?: '—' }}</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $student->status === 'active' ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
                                        {{ $student->status === 'active' ? 'Aktif' : ucfirst($student->status) }}
                                    </span>
                                </td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.students.show', $student) }}">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$students" />
        @endif
    </article>
@endsection
