@php
    $activeEnrollment = $student->enrollments->first(fn ($item) => $item->isActiveOn());
@endphp

@extends('layouts.dashboard')

@section('title', 'Detail Siswa')
@section('heading', 'Detail Siswa')
@section('description', 'Profil pusat siswa, akun portal, pendaftaran, dan penerima rapor.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Siswa / Profil</p>
            <h2>{{ $student->full_name }}</h2>
            <p>{{ $student->school_name ?: 'Sekolah belum diisi' }} · {{ $student->grade_name ?: 'Kelas belum diisi' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-teacher-assignments.create', ['student_id' => $student->id]) }}"><x-dashboard.icon name="teacher" /> Tugaskan Teacher</a>
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.students.edit', $student) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit profil</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-enrollments.create', ['student_id' => $student->id]) }}">
                <x-dashboard.icon name="book" />
                <span>Tambah pendaftaran</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.guardians.create', ['student_id' => $student->id]) }}">
                <x-dashboard.icon name="users" />
                <span>Tambah wali</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Ringkasan profil</h2>
                <p>Data internal yang dipakai lintas cabang dan modul laporan.</p>
            </div>
            <span class="dpe-dashboard-badge {{ $student->status === 'active' ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
                {{ $student->status === 'active' ? 'Aktif' : ucfirst($student->status) }}
            </span>
        </header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Nama siswa</p>
                    <p class="dpe-dashboard-detail__value">{{ $student->full_name }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Mulai belajar</p>
                    <p class="dpe-dashboard-detail__value">{{ $student->began_on?->format('d M Y') ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Sekolah</p>
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $student->school_name ?: 'Belum diisi' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Kelas / tingkat</p>
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $student->grade_name ?: 'Belum diisi' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Username portal</p>
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $student->portalUser?->username ?: 'Belum terhubung' }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Email portal</p>
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $student->portalUser?->email ?: 'Belum diisi' }}</p>
                </div>
                <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                    <p class="dpe-dashboard-detail__label">Catatan internal</p>
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $student->special_notes_internal ?: 'Belum ada catatan internal.' }}</p>
                </div>
            </div>
        </div>
    </article>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Pendaftaran program dan cabang</h2>
                <p>{{ $student->enrollments->count() }} riwayat pendaftaran tercatat.</p>
            </div>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-enrollments.create', ['student_id' => $student->id]) }}">Tambah</a>
        </header>
        @if ($student->enrollments->isEmpty())
            <x-dashboard.empty title="Belum ada pendaftaran" message="Hubungkan siswa ke program dan cabang agar jadwal serta laporan dapat mengikuti penempatannya." action-url="{{ route('superadmin.student-enrollments.create', ['student_id' => $student->id]) }}" action-label="Buat pendaftaran" icon="book" />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead><tr><th>Program</th><th>Cabang</th><th>Mulai</th><th>Berakhir</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($student->enrollments->sortByDesc('starts_on') as $enrollment)
                            @php($enrollmentActive = $enrollment->isActiveOn())
                            <tr>
                                <td><strong>{{ $enrollment->program?->name ?: '—' }}</strong></td>
                                <td>{{ $enrollment->branch?->name ?: '—' }}</td>
                                <td>{{ $enrollment->starts_on?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $enrollment->ends_on?->format('d M Y') ?: 'Berjalan' }}</td>
                                <td><span class="dpe-dashboard-badge {{ $enrollmentActive ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $enrollmentActive ? 'Aktif' : 'Selesai' }}</span></td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-enrollments.show', $enrollment) }}">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Wali dan penerima rapor</h2>
                <p>{{ $student->guardians->count() }} kontak terhubung. Satu kontak dapat ditandai sebagai penerima utama.</p>
            </div>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.guardians.create', ['student_id' => $student->id]) }}">Tambah</a>
        </header>
        @if ($student->guardians->isEmpty())
            <x-dashboard.empty title="Belum ada wali siswa" message="Tambahkan nomor WhatsApp wali untuk kebutuhan komunikasi dan pembagian rapor." action-url="{{ route('superadmin.guardians.create', ['student_id' => $student->id]) }}" action-label="Tambah wali" icon="users" />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead><tr><th>Nama</th><th>Hubungan</th><th>WhatsApp</th><th>Penerima</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($student->guardians as $guardian)
                            <tr>
                                <td><strong>{{ $guardian->full_name }}</strong></td>
                                <td>{{ $guardian->relationship_name ?: '—' }}</td>
                                <td>{{ $guardian->whatsapp_number }}</td>
                                <td><span class="dpe-dashboard-badge {{ $guardian->is_primary ? 'dpe-dashboard-badge--success' : '' }}">{{ $guardian->is_primary ? 'Utama' : 'Alternatif' }}</span></td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.guardians.edit', $guardian) }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.students.index') }}">
            <x-dashboard.icon name="back" />
            <span>Kembali ke daftar siswa</span>
        </a>
    </div>
@endsection
