@extends('layouts.dashboard')

@section('title', 'Detail Siswa')
@section('heading', 'Detail Siswa')
@section('description', 'Periksa profil dan penempatan siswa pada cabang yang Anda kelola.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional Cabang / Siswa</p>
            <h2>{{ $student->full_name }}</h2>
            <p>{{ $student->school_name ?: 'Sekolah belum diisi' }} · {{ $student->grade_name ?: 'Kelas belum diisi' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('admin.students.edit', $student) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit profil</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.students.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Ringkasan profil</h2>
                <p>Data identitas dan akun portal siswa.</p>
            </div>
            <x-dashboard.status :status="$student->status" :label="$student->status === 'active' ? 'Aktif' : ucfirst($student->status)" />
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
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal" style="white-space: pre-line;">{{ $student->special_notes_internal ?: 'Belum ada catatan internal.' }}</p>
                </div>
            </div>
        </div>
    </article>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Program dan cabang</h2>
                <p>{{ $student->enrollments->count() }} riwayat pendaftaran tercatat.</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="book" /> Penempatan</span>
        </header>
        @if ($student->enrollments->isEmpty())
            <x-dashboard.empty
                title="Belum ada pendaftaran"
                message="Siswa ini belum memiliki penempatan program dan cabang aktif. Hubungi Super Admin untuk melanjutkan penempatan."
                icon="book"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead><tr><th>Program</th><th>Cabang</th><th>Mulai</th><th>Berakhir</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($student->enrollments->sortByDesc('starts_on') as $enrollment)
                            @php($enrollmentActive = $enrollment->isActiveOn())
                            <tr>
                                <td><strong>{{ $enrollment->program?->name ?: '—' }}</strong></td>
                                <td>{{ $enrollment->branch?->name ?: '—' }}</td>
                                <td>{{ $enrollment->starts_on?->format('d M Y') ?: '—' }}</td>
                                <td>{{ $enrollment->ends_on?->format('d M Y') ?: 'Berjalan' }}</td>
                                <td><span class="dpe-dashboard-badge {{ $enrollmentActive ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $enrollmentActive ? 'Aktif' : 'Selesai' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>
@endsection
