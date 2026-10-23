@extends('layouts.dashboard')

@section('title', 'Kehadiran Saya')
@section('heading', 'Kehadiran Saya')
@section('description', 'Catat datang–pulang untuk kunjungan cabang dan mulai–selesai untuk private di rumah siswa.')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/attendance.css') }}?v={{ filemtime(public_path('assets/dpe/attendance.css')) }}">
@endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Kehadiran / {{ $todayLabel }}</p>
            <h2>Catat kehadiran mengajar</h2>
            <p>Foto dan lokasi dicatat di awal dan akhir. Anda dapat menutup kunjungan atau sesi sebelum Admin memeriksa buktinya.</p>
        </div>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.dashboard') }}"><x-dashboard.icon name="back" /> Kembali ke dashboard</a>
    </section>

    <article class="dpe-dashboard-card" id="kunjungan-cabang">
        <header class="dpe-dashboard-card__head">
            <div><h2>Kunjungan cabang</h2><p>Satu pasangan datang–pulang untuk setiap kunjungan. Beberapa kelas dalam kunjungan yang sama tidak memerlukan absensi diri tambahan.</p></div>
            <span class="dpe-dashboard-badge">Per kunjungan</span>
        </header>
        <div class="dpe-dashboard-card__body">
            @foreach ($manualBranchDays as $manual)
                <div class="dpe-dashboard-alert" role="status"><x-dashboard.icon name="warning" /><span>{{ $manual->branch?->name }} · {{ match ($manual->attendance_type) { 'sick' => 'Sakit', 'leave' => 'Izin', default => 'Tidak hadir' } }}. {{ $manual->manual_note ?: 'Dicatat oleh Admin.' }}</span></div>
            @endforeach
            @if ($activeVisits->isNotEmpty())
                <div class="dpe-dashboard-attendance-grid">
                    @foreach ($activeVisits as $visit)
                        <article class="dpe-dashboard-attendance-card">
                            <div class="dpe-dashboard-attendance-card__head"><div><h3>{{ $visit->branch?->name }}</h3><p>Datang {{ $visit->check_in_at->format('d M Y H:i') }} · {{ $visit->teacher?->user?->full_name }}</p></div><span class="dpe-dashboard-badge dpe-dashboard-badge--success">Sedang di cabang</span></div>
                            <x-dashboard.attendance-capture :action="route('teacher.attendance.depart', $visit)" label="Absen Pulang" :uid="$visit->id" />
                        </article>
                    @endforeach
                </div>
            @elseif ($todaySessions->contains(fn ($session) => $session->teacherAttendance?->attendance_mode === 'legacy_session' && $session->teacherAttendance?->check_out_at === null))
                <p>Selesaikan catatan sesi lama yang masih terbuka di bagian bawah sebelum memulai kunjungan cabang baru.</p>
            @elseif ($branches->isNotEmpty())
                <div class="dpe-dashboard-attendance-grid">
                    @foreach ($branches as $branch)
                        <article class="dpe-dashboard-attendance-card">
                            <div class="dpe-dashboard-attendance-card__head"><div><h3>{{ $branch->name }}</h3><p>{{ $branch->address }}</p></div><span class="dpe-dashboard-badge">Siap dikunjungi</span></div>
                            @if ($manualBranchDays->contains('branch_id', $branch->id))
                                <p>Hubungi Admin untuk penyesuaian status hari ini sebelum mencatat kedatangan.</p>
                            @else
                                <x-dashboard.attendance-capture :action="route('teacher.attendance.arrive', $branch)" label="Absen Datang" :uid="$branch->id" />
                            @endif
                        </article>
                    @endforeach
                </div>
                <p class="dpe-dashboard-field__hint dpe-attendance-hint">Datang kembali setelah pulang? Catat Absen Datang baru. Pemeriksaan kunjungan sebelumnya tidak menghalangi kunjungan berikutnya.</p>
            @else
                <x-dashboard.empty title="Belum ada cabang aktif untuk absensi pribadi" message="Absensi datang tersedia bagi akun Teacher dengan penugasan cabang aktif." icon="calendar" />
            @endif
        </div>
    </article>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced" id="private-hari-ini">
        <header class="dpe-dashboard-card__head"><div><h2>Private di rumah siswa</h2><p>Sesi hari ini dan catatan sebelumnya yang masih perlu ditutup.</p></div><span class="dpe-dashboard-badge">Per sesi</span></header>
        @if ($todaySessions->isEmpty())
            <x-dashboard.empty title="Belum ada sesi private hari ini" message="Jadwal private muncul setelah lokasi sesi ditetapkan sebagai rumah siswa." icon="calendar" />
        @else
            <div class="dpe-dashboard-card__body dpe-dashboard-attendance-grid">
                @foreach ($todaySessions as $session)
                    @php
                        $attendance = $session->teacherAttendance;
                        $started = $attendance?->check_in_at !== null;
                        $finished = $attendance?->check_out_at !== null;
                        $manual = $attendance?->isManualException() ?? false;
                    @endphp
                    <article class="dpe-dashboard-attendance-card">
                        <div class="dpe-dashboard-attendance-card__head">
                            <div><p class="dpe-dashboard-attendance-card__time">{{ $session->planned_start_at?->format('d M Y H:i') }}–{{ $session->planned_end_at?->format('H:i') }}</p><h3>{{ $session->program?->name ?: 'Sesi private' }}</h3><p>{{ $session->branch?->name }} · {{ $session->topic ?: 'Topik belum diisi' }}</p></div>
                            <span class="dpe-dashboard-badge">{{ $attendance?->attendance_mode === 'legacy_session' ? 'Sesi lama' : 'Rumah siswa' }}</span>
                        </div>
                        @if ($manual)
                            <p>{{ match ($attendance->attendance_type) { 'sick' => 'Sakit', 'leave' => 'Izin', default => 'Tidak hadir' } }} · {{ $attendance->manual_note ?: 'Dicatat oleh Admin.' }}</p>
                        @elseif ($finished)
                            <div class="dpe-dashboard-attendance-complete"><x-dashboard.icon name="check" /><div><strong>Sesi selesai</strong><span>Mulai {{ $attendance->check_in_at?->format('H:i') }} · Selesai {{ $attendance->check_out_at?->format('H:i') }}</span></div></div>
                        @elseif ($session->isCancelled() && ! $started)
                            <p>Sesi dibatalkan.</p>
                        @else
                            @if ($started)<p>Mulai {{ $attendance->check_in_at->format('d M Y H:i') }}. Ambil bukti baru saat selesai.</p>@endif
                            <x-dashboard.attendance-capture :action="route($started ? 'teacher.attendance.check-out' : 'teacher.attendance.check-in', $session)" :label="$started ? 'Absen Selesai' : 'Absen Mulai'" :uid="$session->id" />
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </article>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Jadwal di cabang hari ini</h2><p>Jadwal ini menjadi acuan kelas. Absensi diri tetap dicatat melalui Kunjungan cabang.</p></div></header>
        @if ($branchSessions->isEmpty())
            <x-dashboard.empty title="Belum ada jadwal cabang hari ini" message="Kunjungan tetap dapat dicatat pada cabang penugasan Anda." icon="calendar" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table"><thead><tr><th>Waktu</th><th>Cabang</th><th>Program / topik</th><th>Status jadwal</th></tr></thead><tbody>
                @foreach ($branchSessions as $session)<tr><td>{{ $session->planned_start_at?->format('H:i') }}–{{ $session->planned_end_at?->format('H:i') }}</td><td>{{ $session->branch?->name }}</td><td><strong>{{ $session->program?->name }}</strong><br>{{ $session->topic }}</td><td>{{ $session->isCancelled() ? 'Dibatalkan' : 'Terjadwal' }}</td></tr>@endforeach
            </tbody></table></div>
        @endif
    </article>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced" id="riwayat-absensi">
        <header class="dpe-dashboard-card__head"><div><h2>Riwayat absensi</h2><p>Pelaksanaan dan hasil pemeriksaan ditampilkan terpisah. {{ $recentAttendances->count() }} catatan terbaru.</p></div></header>
        @if ($recentAttendances->isEmpty())
            <x-dashboard.empty title="Belum ada riwayat absensi" message="Kunjungan dan sesi yang dicatat akan tampil di sini." icon="clock" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table"><thead><tr><th>Tanggal / jenis</th><th>Cabang / guru</th><th>Awal</th><th>Akhir</th><th>Pelaksanaan</th><th>Pemeriksaan</th></tr></thead><tbody>
                @foreach ($recentAttendances as $record)
                    <tr><td><strong>{{ $record->recordDate()?->format('d M Y') ?: '—' }}</strong><br>{{ $record->modeLabel() }}</td><td>{{ $record->branch?->name }}<br>{{ $record->teacher?->user?->full_name }}</td><td>{{ $record->check_in_at?->format('d M H:i') ?: '—' }}</td><td>{{ $record->check_out_at?->format('d M H:i') ?: '—' }}</td><td>{{ $record->isManualException() ? match ($record->attendance_type) { 'sick' => 'Sakit', 'leave' => 'Izin', default => 'Tidak hadir' } : ($record->check_out_at ? 'Selesai' : 'Berlangsung') }}</td><td><span class="dpe-dashboard-badge {{ $record->status === 'approved' ? 'dpe-dashboard-badge--success' : ($record->status === 'needs_correction' ? 'dpe-dashboard-badge--danger' : '') }}">{{ match ($record->status) { 'approved' => 'Disetujui', 'needs_correction' => 'Perlu koreksi', 'pending_review' => 'Menunggu pemeriksaan', 'recorded' => 'Dicatat Admin', default => 'Belum diperiksa' } }}</span>@if ($record->status === 'approved')<x-dashboard.approval :user="$record->reviewedBy" :at="$record->reviewed_at" :timezone="$record->branch?->timezone_name" />
                        @endif
                        @if ($record->review_note)<p>{{ $record->review_note }}</p>@endif</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </article>
@endsection

@push('scripts')
    <script src="{{ asset('assets/dpe/attendance.js') }}?v={{ filemtime(public_path('assets/dpe/attendance.js')) }}" defer></script>
@endpush
