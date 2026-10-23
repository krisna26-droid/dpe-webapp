@extends('layouts.dashboard')

@section('title', 'Dashboard Admin Cabang')
@section('heading', 'Dashboard')
@section('description', 'Kelola aktivitas belajar dan laporan pada cabang Anda.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Overview cabang</p>
            <h2>Ruang kerja admin cabang.</h2>
            <p>Mulai dari data siswa dan guru, lalu pantau siklus laporan yang sedang berjalan.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('admin.students.create') }}">
                <x-dashboard.icon name="student" />
                <span>Tambah siswa</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--metrics" aria-label="Ringkasan cabang">
        <x-dashboard.metric-card
            label="Siswa aktif"
            :value="number_format($activeStudents, 0, ',', '.')"
            :meta="$branchScopeLabel.' · pendaftaran aktif'"
            icon="student"
        />
        <x-dashboard.metric-card
            label="Guru aktif"
            :value="number_format($activeTeachers, 0, ',', '.')"
            :meta="$branchScopeLabel.' · penugasan atau tanggung jawab laporan'"
            tone="green"
            icon="teacher"
        />
        <x-dashboard.metric-card
            label="Sesi hari ini"
            :value="number_format($sessionsToday, 0, ',', '.')"
            :meta="$todayLabel.' · sesi terjadwal'"
            tone="orange"
            icon="calendar"
        />
        <x-dashboard.metric-card
            label="Review laporan"
            :value="number_format($reportsForReview, 0, ',', '.')"
            :meta="$reportsForReview > 0 ? 'Menunggu persetujuan' : 'Tidak ada antrean review'"
            tone="purple"
            icon="report"
        />
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--main">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Aktivitas cabang</h2>
                    <p>{{ $branchScopeLabel }} · sesi hari ini dan laporan yang perlu ditinjau.</p>
                </div>
                <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.students.index') }}">Lihat siswa</a>
            </header>
            <div class="dpe-dashboard-card__body dpe-dashboard-card__body--flush">
                @if (isset($dashboardLoading) && $dashboardLoading)
                    <x-dashboard.loading rows="4" :show-header="false" />
                @elseif (isset($dashboardError) && $dashboardError)
                    <x-dashboard.error title="Aktivitas belum tersedia" message="Data cabang belum bisa dimuat." />
                @elseif ($todaySessions->isNotEmpty() || $pendingReports->isNotEmpty())
                    <div class="dpe-dashboard-list">
                        @foreach ($todaySessions as $session)
                            @php
                                $sessionStatus = [
                                    'scheduled' => 'Terjadwal',
                                    'ongoing' => 'Berlangsung',
                                    'in_progress' => 'Berlangsung',
                                    'completed' => 'Selesai',
                                ][$session->status] ?? ucfirst((string) $session->status);
                                $teacherName = trim((string) ($session->teacher?->user?->full_name ?: $session->teacher?->user?->username ?: 'Guru belum dipilih'));
                            @endphp
                            <div class="dpe-dashboard-list__item">
                                <span class="dpe-dashboard-list__icon"><x-dashboard.icon name="calendar" /></span>
                                <span class="dpe-dashboard-list__copy">
                                    <strong>{{ $session->topic ?: 'Sesi belajar' }}</strong>
                                    <span>{{ $session->planned_start_at?->format('H:i') }}–{{ $session->planned_end_at?->format('H:i') }} · {{ $teacherName }} · {{ $session->branch?->name ?: 'Cabang' }}</span>
                                </span>
                                <x-dashboard.status :status="$session->status" :label="$sessionStatus" />
                            </div>
                        @endforeach

                        @foreach ($pendingReports as $report)
                            @php
                                $studentName = trim((string) ($report->student?->full_name ?: 'Siswa'));
                                $teacherName = trim((string) ($report->teacher?->user?->full_name ?: $report->teacher?->user?->username ?: 'Guru'));
                            @endphp
                            <div class="dpe-dashboard-list__item">
                                <span class="dpe-dashboard-list__icon"><x-dashboard.icon name="report" /></span>
                                <span class="dpe-dashboard-list__copy">
                                    <strong>Review rapor {{ $studentName }}</strong>
                                    <span>{{ $teacherName }} · {{ $report->branch?->name ?: 'Cabang' }} · jatuh tempo {{ $report->due_on?->format('d/m/Y') ?: 'belum diatur' }}</span>
                                </span>
                                <span class="dpe-dashboard-badge dpe-dashboard-badge--warning">Review</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-dashboard.empty
                        title="Belum ada aktivitas cabang"
                        message="Sesi hari ini dan laporan menunggu review akan tampil setelah data operasional tersedia."
                        :action-url="route('admin.students.index')"
                        action-label="Buka data siswa"
                        icon="student"
                    />
                @endif
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Akses cepat</h2>
                    <p>Alur kerja inti admin cabang.</p>
                </div>
            </header>
            <div class="dpe-dashboard-list">
                <a class="dpe-dashboard-list__item" href="{{ route('admin.students.index') }}">
                    <span class="dpe-dashboard-list__icon"><x-dashboard.icon name="student" /></span>
                    <span class="dpe-dashboard-list__copy"><strong>Data siswa</strong><span>Kelola profil dan status siswa.</span></span>
                    <x-dashboard.icon name="chevron-right" />
                </a>
                <a class="dpe-dashboard-list__item" href="{{ route('admin.teachers.index') }}">
                    <span class="dpe-dashboard-list__icon"><x-dashboard.icon name="teacher" /></span>
                    <span class="dpe-dashboard-list__copy"><strong>Data guru</strong><span>Lihat tim pengajar di cabang.</span></span>
                    <x-dashboard.icon name="chevron-right" />
                </a>
                <a class="dpe-dashboard-list__item" href="{{ route('admin.teacher-attendance.index') }}">
                    <span class="dpe-dashboard-list__icon"><x-dashboard.icon name="clock" /></span>
                    <span class="dpe-dashboard-list__copy"><strong>Kehadiran guru</strong><span>Periksa foto, lokasi, dan hasil validasi absensi.</span></span>
                    <x-dashboard.icon name="chevron-right" />
                </a>
                <a class="dpe-dashboard-list__item" href="{{ route('admin.report-cycles.index') }}">
                    <span class="dpe-dashboard-list__icon"><x-dashboard.icon name="report" /></span>
                    <span class="dpe-dashboard-list__copy"><strong>Siklus laporan</strong><span>Buka periode laporan berjalan.</span></span>
                    <x-dashboard.icon name="chevron-right" />
                </a>
            </div>
        </article>
    </section>

    <section class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Status proses cabang</h2>
                <p>Ringkasan tindakan yang perlu diperhatikan hari ini.</p>
            </div>
        </header>
        @if ($reportsForReview > 0 || $sessionsToday > 0)
            <div class="dpe-dashboard-list">
                @if ($reportsForReview > 0)
                    <div class="dpe-dashboard-list__item">
                        <span class="dpe-dashboard-list__icon"><x-dashboard.icon name="report" /></span>
                        <span class="dpe-dashboard-list__copy"><strong>{{ number_format($reportsForReview, 0, ',', '.') }} laporan menunggu review</strong><span>Periksa laporan yang sudah diajukan guru.</span></span>
                        <span class="dpe-dashboard-badge dpe-dashboard-badge--warning">Perlu ditinjau</span>
                    </div>
                @endif
                @if ($sessionsToday > 0)
                    <div class="dpe-dashboard-list__item">
                        <span class="dpe-dashboard-list__icon"><x-dashboard.icon name="calendar" /></span>
                        <span class="dpe-dashboard-list__copy"><strong>{{ number_format($sessionsToday, 0, ',', '.') }} sesi terjadwal hari ini</strong><span>Pastikan guru dan siswa siap mengikuti jadwal.</span></span>
                        <span class="dpe-dashboard-badge dpe-dashboard-badge--success">Terjadwal</span>
                    </div>
                @endif
            </div>
        @else
            <div class="dpe-dashboard-card__body">
                <x-dashboard.empty
                    title="Belum ada proses yang menunggu"
                    message="Tidak ada sesi atau laporan yang membutuhkan tindak lanjut hari ini."
                    icon="clock"
                />
            </div>
        @endif
    </section>
@endsection
