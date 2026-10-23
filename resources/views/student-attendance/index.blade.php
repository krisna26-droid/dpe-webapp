@extends('layouts.dashboard')

@section('title', 'Kehadiran Siswa')
@section('heading', 'Kehadiran Siswa')
@section('description', 'Catat kehadiran siswa berdasarkan tanggal pertemuan dan kelas yang ditugaskan.')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/student-attendance.css') }}?v={{ filemtime(public_path('assets/dpe/student-attendance.css')) }}">
@endpush
@push('scripts')
    <script src="{{ asset('assets/dpe/student-attendance.js') }}?v={{ filemtime(public_path('assets/dpe/student-attendance.js')) }}" defer></script>
@endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Kehadiran</p>
            <h2>Absensi siswa</h2>
            <p>Pilih kelas dan bulan. Kotak kosong berarti kehadiran belum dicatat.</p>
        </div>
        @if ($routeBase === 'superadmin.student-attendance')
            <div class="dpe-dashboard-page-header__actions">
                @if ($filters['class_group_id'] !== 'private')
                    <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.class-group-memberships.create', ['class_group_id' => $filters['class_group_id']]) }}"><x-dashboard.icon name="plus" />Tambah anggota kelas</a>
                @endif
                <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.students.index') }}">Siswa &amp; Pendaftaran</a>
            </div>
        @endif
    </section>

    <form method="GET" action="{{ route($routeBase.'.index') }}" class="dpe-dashboard-form dpe-student-attendance-filters">
        <input type="hidden" name="month" value="{{ $filters['month'] }}" data-attendance-month>
        <div class="dpe-dashboard-form-grid dpe-dashboard-form-grid--three">
            <div class="dpe-dashboard-field">
                <label for="attendance-period">Awal rentang tiga bulan</label>
                <input type="month" id="attendance-period" name="period" value="{{ $filters['period'] }}" required>
            </div>
            <div class="dpe-dashboard-field">
                <label for="attendance-branch">Cabang</label>
                <select name="branch_id" id="attendance-branch" data-attendance-branch>
                    <option value="">Semua cabang yang dapat diakses</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') === $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dpe-dashboard-field">
                <label for="attendance-group">Kelas</label>
                <select name="class_group_id" id="attendance-group">
                    <option value="private" @selected($filters['class_group_id'] === 'private')>Private / tanpa kelompok</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected($filters['class_group_id'] === $group->id)>{{ $group->name }} — {{ $group->branch?->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dpe-dashboard-field">
                <label for="attendance-student">Siswa</label>
                <select name="student_id" id="attendance-student">
                    <option value="">Semua peserta</option>
                    @foreach ($studentOptions as $option)
                        <option value="{{ $option->id }}" @selected(($filters['student_id'] ?? '') === $option->id)>{{ $option->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dpe-dashboard-field">
                <label for="attendance-search">Cari nama siswa</label>
                <input id="attendance-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="80" placeholder="Nama siswa">
            </div>
            <div class="dpe-student-attendance-filter-actions">
                <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit"><x-dashboard.icon name="search" />Tampilkan</button>
                <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index') }}">Reset</a>
            </div>
        </div>
    </form>

    <nav class="dpe-student-attendance-period" aria-label="Bulan kehadiran">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index', array_merge($filters, ['period' => $period->copy()->subMonths(3)->format('Y-m'), 'month' => $period->copy()->subMonths(3)->format('Y-m'), 'student_id' => null])) }}" aria-label="Tiga bulan sebelumnya"><x-dashboard.icon name="back" /></a>
        <div class="dpe-student-attendance-tabs">
            @foreach ($months as $tab)
                <a class="dpe-student-attendance-tab {{ $tab->format('Y-m') === $filters['month'] ? 'is-active' : '' }}" @if ($tab->format('Y-m') === $filters['month']) aria-current="page" @endif href="{{ route($routeBase.'.index', array_merge($filters, ['month' => $tab->format('Y-m'), 'student_id' => null])) }}">{{ $tab->locale('id')->translatedFormat('F Y') }}</a>
            @endforeach
        </div>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index', array_merge($filters, ['period' => $period->copy()->addMonths(3)->format('Y-m'), 'month' => $period->copy()->addMonths(3)->format('Y-m'), 'student_id' => null])) }}" aria-label="Tiga bulan berikutnya">&rarr;</a>
    </nav>

    <div class="dpe-student-attendance-metrics" data-attendance-overall-summary data-attendance-summary="{{ json_encode($summary) }}">
        <div><span>Total hadir</span><strong data-attendance-count="attended_count">{{ $summary['attended_count'] }}</strong><small><span data-attendance-count="present">{{ $summary['present'] }}</span> tepat waktu + <span data-attendance-count="late">{{ $summary['late'] }}</span> terlambat</small></div>
        <div><span>Tidak hadir / sakit / izin</span><strong data-attendance-count="absent,sick,permission">{{ $summary['absent'] + $summary['sick'] + $summary['permission'] }}</strong><small><span data-attendance-count="absent">{{ $summary['absent'] }}</span> tidak hadir · <span data-attendance-count="sick">{{ $summary['sick'] }}</span> sakit · <span data-attendance-count="permission">{{ $summary['permission'] }}</span> izin</small></div>
        <div><span>Belum dicatat</span><strong data-attendance-count="unrecorded">{{ $summary['unrecorded'] }}</strong><small>Dari <span data-attendance-count="meeting_count">{{ $summary['meeting_count'] }}</span> catatan pertemuan yang berlangsung</small></div>
        <div><span>Dijadwalkan ulang</span><strong data-attendance-count="rescheduled">{{ $summary['rescheduled'] }}</strong><small>Pelaksanaan mengikuti tanggal pengganti</small></div>
    </div>
    <p class="dpe-student-attendance-notice" data-attendance-completeness @if (! $summary['unrecorded'] && ! $summary['unknown']) hidden @endif>Rekap belum lengkap. <span data-attendance-count="unrecorded">{{ $summary['unrecorded'] }}</span> kehadiran belum dicatat. <span data-attendance-unknown-note @if (! $summary['unknown']) hidden @endif><span data-attendance-count="unknown">{{ $summary['unknown'] }}</span> status lama perlu ditinjau.</span></p>
    @if ($rows->isEmpty())
        @include('student-attendance._participant-help', ['classGroupId' => $filters['class_group_id'] !== 'private' ? $filters['class_group_id'] : null])
    @endif

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div><h2>{{ $month->locale('id')->translatedFormat('F Y') }}</h2><p>Target normal 8 pertemuan per siswa; tambahan pertemuan tetap dapat dicatat.</p></div>
            <span class="dpe-dashboard-badge">{{ $rows->count() }} siswa</span>
        </header>
        @if ($rows->isEmpty())
            <x-dashboard.empty title="Belum ada peserta untuk pilihan ini" message="Pilih kelas lain atau periksa daftar peserta sesi. Peserta private ditetapkan oleh Admin atau Super Admin." icon="student" />
        @else
            <p class="dpe-student-attendance-help">Klik checkbox untuk membuka konfirmasi, pilih status, lalu tekan Ya, simpan. Kehadiran dan rekap diperbarui tanpa memuat ulang halaman. Klik status untuk membuka detail dan riwayat.</p>
            <div class="dpe-dashboard-table-wrap dpe-student-attendance-grid-wrap" tabindex="0" aria-label="Tabel kehadiran; geser untuk melihat seluruh pertemuan">
                <table class="dpe-dashboard-table dpe-student-attendance-grid">
                    <thead>
                        <tr>
                            <th class="dpe-student-attendance-student">Siswa / mulai les</th>
                            @for ($i = 0; $i < max(8, $sessions->count()); $i++)
                                <th>P{{ $i + 1 }}@if ($sessions->has($i))<small>{{ $sessions[$i]->actual_start_at?->format('d M') ?: $sessions[$i]->planned_start_at->format('d M') }}</small>@else<small>Belum ada jadwal</small>@endif</th>
                            @endfor
                            <th>Rekap siswa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="dpe-student-attendance-student"><strong>{{ $row['student']->full_name }}</strong><small>Mulai {{ $row['student']->began_on->format('d M Y') }}</small></td>
                                @for ($i = 0; $i < max(8, $sessions->count()); $i++)
                                    <td data-attendance-cell-view>
                                        @if ($sessions->has($i))
                                            @php
                                                $session = $sessions[$i];
                                                $cell = $row['cells'][$session->id];
                                                $editUrl = route($routeBase.'.edit', [$session, $row['student']]);
                                            @endphp
                                            @if ($cell['is_member'])
                                                @include('student-attendance._quick', ['attendanceSession' => $session, 'student' => $row['student']])
                                                <a class="dpe-student-attendance-status dpe-student-attendance-status--{{ array_key_exists($cell['status'] ?? '', $statuses) ? $cell['status'] : 'empty' }}" href="{{ $editUrl }}" data-attendance-status>{{ $cell['label'] }}</a>
                                                <small>{{ $cell['date']->format('H:i') }}</small>
                                            @else
                                                <span class="dpe-student-attendance-unused">{{ $cell['before_join'] ? 'Sebelum mulai les' : 'Bukan peserta' }}</span>
                                            @endif
                                        @else
                                            <input type="checkbox" disabled aria-label="Belum ada jadwal pertemuan {{ $i + 1 }} untuk {{ $row['student']->full_name }}">
                                            <small>—</small>
                                        @endif
                                    </td>
                                @endfor
                                @include('student-attendance._recap')
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>

    <article class="dpe-dashboard-card dpe-student-attendance-schedule">
        <header class="dpe-dashboard-card__head"><div><h2>Jadwal dan peserta sesi</h2><p>Buka sesi untuk melihat peserta dan mencatat kehadiran.</p></div><span class="dpe-dashboard-badge">{{ $schedule->total() }} sesi</span></header>
        @if ($schedule->isEmpty())
            <x-dashboard.empty title="Belum ada jadwal pada bulan ini" message="Jadwal tampil setelah sesi belajar dibuat oleh Super Admin." icon="calendar" />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table"><thead><tr><th>Pertemuan</th><th>Cabang</th><th>Kelas / program</th><th>Guru</th><th>Aksi</th></tr></thead><tbody>
                    @foreach ($schedule as $session)
                        <tr><td><strong>{{ ($session->actual_start_at ?? $session->planned_start_at)->format('d M Y') }}</strong><br>{{ ($session->actual_start_at ?? $session->planned_start_at)->format('H:i') }}</td><td>{{ $session->branch?->name }}</td><td><strong>{{ $session->classGroup?->name ?: 'Private / tanpa kelompok' }}</strong><br>{{ $session->program?->name }}</td><td>{{ $session->teacher?->user?->full_name ?: $session->teacher?->user?->username }}</td><td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($routeBase.'.show', $session) }}">Buka absensi</a></td></tr>
                    @endforeach
                </tbody></table>
            </div>
            <x-dashboard.pagination :paginator="$schedule" />
        @endif
    </article>
    @include('student-attendance._confirm')
@endsection
