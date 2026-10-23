@extends('layouts.dashboard')

@section('title', 'Monitoring Kehadiran Guru')
@section('heading', 'Monitoring Kehadiran Guru')
@section('description', 'Periksa bukti absensi, status sesi, dan hasil validasi guru pada cabang yang menjadi tanggung jawab Anda.')

@php
    $statusMeta = [
        'in_progress' => ['label' => 'Sedang mengajar'],
        'pending_review' => ['label' => 'Menunggu pemeriksaan'],
        'approved' => ['label' => 'Disetujui'],
        'needs_correction' => ['label' => 'Perlu koreksi'],
        'recorded' => ['label' => 'Dicatat Admin'],
    ];
    $attendanceTypeMeta = [
        'present' => ['label' => 'Hadir'],
        'absent' => ['label' => 'Tidak hadir'],
        'leave' => ['label' => 'Izin'],
        'sick' => ['label' => 'Sakit'],
    ];
@endphp

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Absensi Guru</p>
            <h2>Absensi guru</h2>
            <p>Periksa kunjungan cabang dan sesi private, termasuk foto, lokasi, dan pengecualian yang perlu ditinjau.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--metrics dpe-dashboard-grid--metrics-five" aria-label="Ringkasan absensi guru">
        <x-dashboard.metric-card
            label="Total catatan"
            :value="number_format($stats['total'], 0, ',', '.')"
            :meta="$stats['branch_visits'].' kunjungan cabang · '.$stats['private_sessions'].' sesi private'"
            icon="clock"
        />
        <x-dashboard.metric-card
            label="Menunggu pemeriksaan"
            :value="number_format($stats['pending_review'], 0, ',', '.')"
            meta="Perlu tindakan Admin"
            tone="orange"
            icon="warning"
        />
        <x-dashboard.metric-card
            label="Disetujui"
            :value="number_format($stats['approved'], 0, ',', '.')"
            meta="Validasi selesai"
            tone="green"
            icon="check"
        />
        <x-dashboard.metric-card
            label="Perlu koreksi"
            :value="number_format($stats['needs_correction'], 0, ',', '.')"
            meta="Menunggu tindak lanjut guru"
            tone="purple"
            icon="edit"
        />
        <x-dashboard.metric-card
            label="Belum ada catatan"
            :value="number_format($stats['without_record'], 0, ',', '.')"
            :meta="$pendingBranchDays->count().' hari cabang · '.$pendingSessions->total().' sesi private'"
            tone="orange"
            icon="warning"
        />
    </section>

    <article class="dpe-dashboard-card dpe-dashboard-attendance-filter-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Filter monitoring</h2>
                <p>Gunakan periode, cabang, guru, atau status untuk mempersempit daftar.</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="settings" /> Filter</span>
        </header>
        <div class="dpe-dashboard-card__body">
            <form method="GET" action="{{ route('admin.teacher-attendance.index') }}">
                <div class="dpe-dashboard-form-grid dpe-dashboard-form-grid--three">
                    <div class="dpe-dashboard-field">
                        <label for="attendance_mode">Mode absensi</label>
                        <select id="attendance_mode" name="attendance_mode">
                            <option value="">Semua mode</option>
                            @foreach ($modeOptions as $mode => $label)<option value="{{ $mode }}" @selected($filters['attendance_mode'] === $mode)>{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="dpe-dashboard-field">
                        <label for="date_from">Dari tanggal</label>
                        <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] }}">
                    </div>
                    <div class="dpe-dashboard-field">
                        <label for="date_to">Sampai tanggal</label>
                        <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] }}">
                    </div>
                    <div class="dpe-dashboard-field">
                        <label for="branch_id">Cabang</label>
                        <select id="branch_id" name="branch_id">
                            <option value="">Semua cabang dalam akses</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) $filters['branch_id'] === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="dpe-dashboard-field">
                        <label for="teacher_id">Guru</label>
                        <select id="teacher_id" name="teacher_id">
                            <option value="">Semua guru</option>
                            @foreach ($teachers as $teacher)
                                @php
                                    $teacherName = $teacher->user?->full_name ?: ($teacher->user?->username ?: $teacher->id);
                                @endphp
                                <option value="{{ $teacher->id }}" @selected((string) $filters['teacher_id'] === (string) $teacher->id)>{{ $teacherName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="dpe-dashboard-field">
                        <label for="status">Status validasi</label>
                        <select id="status" name="status">
                            <option value="">Semua status</option>
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="dpe-dashboard-field">
                        <label for="attendance_type">Jenis catatan</label>
                        <select id="attendance_type" name="attendance_type">
                            <option value="">Semua jenis catatan</option>
                            @foreach ($attendanceTypeOptions as $value => $label)
                                <option value="{{ $value }}" @selected($filters['attendance_type'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="dpe-dashboard-form-actions dpe-dashboard-attendance-filter-actions">
                    <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.teacher-attendance.index') }}">Reset filter</a>
                    <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
                        <x-dashboard.icon name="search" />
                        <span>Terapkan filter</span>
                    </button>
                </div>
            </form>
        </div>
    </article>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Private belum memiliki absensi</h2>
                <p>Sesi private di rumah siswa yang sudah waktunya dimulai. Tandai ketidakhadiran setelah dikonfirmasi.</p>
            </div>
            <span class="dpe-dashboard-badge {{ $pendingSessions->total() > 0 ? 'dpe-dashboard-badge--danger' : 'dpe-dashboard-badge--success' }}">
                <x-dashboard.icon name="warning" /> {{ $pendingSessions->total() }} sesi
            </span>
        </header>

        @if ($pendingSessions->isEmpty())
            <x-dashboard.empty
                title="Tidak ada sesi yang tertunda"
                message="Semua sesi pada periode dan filter ini sudah memiliki catatan absensi, atau status validasi sedang difilter secara khusus."
                icon="check"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table dpe-dashboard-attendance-exception-table">
                    <thead>
                        <tr>
                            <th>Sesi</th>
                            <th>Guru / cabang</th>
                            <th>Program / kelas</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingSessions as $lessonSession)
                            @php
                                $exceptionTeacherName = $lessonSession->teacher?->user?->full_name ?: ($lessonSession->teacher?->user?->username ?: 'Guru');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $lessonSession->planned_start_at?->format('d M Y') ?: '—' }}</strong><br>
                                    <span>{{ $lessonSession->planned_start_at?->format('H:i') ?: '—' }}–{{ $lessonSession->planned_end_at?->format('H:i') ?: '—' }}</span>
                                    @if ($lessonSession->topic)<br><span>{{ $lessonSession->topic }}</span>@endif
                                </td>
                                <td>
                                    <strong>{{ $exceptionTeacherName }}</strong><br>
                                    <span>{{ $lessonSession->branch?->name ?: 'Cabang' }}</span>
                                </td>
                                <td>
                                    <strong>{{ $lessonSession->program?->name ?: '—' }}</strong><br>
                                    <span>{{ $lessonSession->classGroup?->name ?: 'Kelas individual' }}</span>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.teacher-attendance.sessions.mark', $lessonSession) }}" class="dpe-dashboard-attendance-exception-form">
                                        @csrf
                                        @foreach (['date_from', 'date_to', 'branch_id', 'teacher_id'] as $filterKey)
                                            @if (!empty($filters[$filterKey]))
                                                <input type="hidden" name="{{ $filterKey }}" value="{{ $filters[$filterKey] }}">
                                            @endif
                                        @endforeach
                                        <div class="dpe-dashboard-field">
                                            <label for="exception-type-{{ $lessonSession->id }}">Status</label>
                                            <select id="exception-type-{{ $lessonSession->id }}" name="attendance_type" required>
                                                @foreach ($exceptionTypeOptions as $type => $label)
                                                    <option value="{{ $type }}" @selected(old('attendance_type', 'absent') === $type)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('attendance_type')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                                        </div>
                                        <div class="dpe-dashboard-field">
                                            <label for="exception-note-{{ $lessonSession->id }}">Catatan <span>(wajib untuk izin/sakit)</span></label>
                                            <textarea id="exception-note-{{ $lessonSession->id }}" name="manual_note" rows="2" maxlength="1000" placeholder="Contoh: Izin sakit, ada surat dari wali.">{{ old('manual_note') }}</textarea>
                                            @error('manual_note')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                                        </div>
                                        @error('attendance')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                                        <button class="dpe-dashboard-button dpe-dashboard-button--soft" type="submit">
                                            <x-dashboard.icon name="check" />
                                            <span>Simpan status</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$pendingSessions" />
        @endif
    </article>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Hari cabang belum memiliki catatan</h2><p>Satu baris per guru, cabang, dan tanggal dengan jadwal yang sudah dimulai. Belum tercatat tidak otomatis berarti tidak hadir.</p></div><span class="dpe-dashboard-badge">{{ $pendingBranchDays->count() }} hari</span></header>
        @if ($pendingBranchDays->isEmpty())
            <x-dashboard.empty title="Tidak ada hari cabang yang tertunda" message="Hari dengan jadwal pada periode ini sudah memiliki catatan, atau sedang difilter." icon="check" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-dashboard-attendance-exception-table"><thead><tr><th>Tanggal</th><th>Guru / cabang</th><th>Pencatatan ketidakhadiran</th></tr></thead><tbody>
            @foreach ($pendingBranchDays as $day)
                <tr><td><strong>{{ $day->planned_start_at?->format('d M Y') }}</strong><br>Jadwal pertama {{ $day->planned_start_at?->format('H:i') }}</td><td>{{ $day->teacher?->user?->full_name }}<br>{{ $day->branch?->name }}</td><td>
                    <form method="POST" action="{{ route('admin.teacher-attendance.sessions.mark', $day->id) }}" class="dpe-dashboard-attendance-exception-form">
                        @csrf
                        @foreach (['date_from', 'date_to', 'branch_id', 'teacher_id'] as $key)@if (!empty($filters[$key]))<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif @endforeach
                        <div class="dpe-dashboard-field"><label for="day-type-{{ $day->id }}">Status hari ini</label><select id="day-type-{{ $day->id }}" name="attendance_type" required>@foreach ($exceptionTypeOptions as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="dpe-dashboard-field"><label for="day-note-{{ $day->id }}">Catatan (wajib untuk izin/sakit)</label><textarea id="day-note-{{ $day->id }}" name="manual_note" rows="2" maxlength="1000">{{ old('manual_note') }}</textarea></div>
                        <button class="dpe-dashboard-button dpe-dashboard-button--soft" type="submit">Simpan status hari</button>
                    </form>
                </td></tr>
            @endforeach
            </tbody></table></div>
        @endif
    </article>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar absensi guru</h2>
                <p>{{ $attendances->total() }} catatan pada periode {{ \Illuminate\Support\Carbon::parse($filters['date_from'])->format('d M Y') }}–{{ \Illuminate\Support\Carbon::parse($filters['date_to'])->format('d M Y') }}.</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="report" /> A05 / S07</span>
        </header>

        @if ($attendances->isEmpty())
            <x-dashboard.empty
                title="Belum ada catatan absensi"
                message="Catatan guru akan tampil setelah sesi selesai dan absensinya dikirim untuk pemeriksaan."
                icon="clock"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table dpe-dashboard-attendance-table">
                    <thead>
                        <tr>
                            <th>Tanggal / mode</th>
                            <th>Guru / cabang</th>
                            <th>Pelaksanaan</th>
                            <th>Status validasi</th>
                            <th>Bukti lokasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attendances as $attendance)
                            @php
                                $hasCheckIn = $attendance->check_in_at !== null;
                                $hasCheckOut = $attendance->check_out_at !== null;
                                $attendanceType = $attendance->attendance_type ?: 'present';
                                $sessionStatus = $attendanceType !== 'present'
                                    ? ($attendanceTypeMeta[$attendanceType]['label'] ?? 'Ketidakhadiran')
                                    : (! $hasCheckIn
                                    ? 'Belum absen masuk'
                                    : ($hasCheckOut ? 'Selesai mengajar' : 'Sedang mengajar'));
                                $meta = $attendanceType !== 'present'
                                    ? ($attendanceTypeMeta[$attendanceType] ?? ['label' => 'Ketidakhadiran'])
                                    : ($statusMeta[$attendance->status] ?? ['label' => ucfirst((string) $attendance->status)]);
                                $teacherName = $attendance->teacher?->user?->full_name ?: ($attendance->teacher?->user?->username ?: 'Guru');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.teacher-attendance.show', $attendance) }}">
                                        <strong>{{ $attendance->recordDate()?->format('d M Y') ?: '—' }}</strong>
                                    </a><br>
                                    <span>{{ $attendance->modeLabel() }}</span><br>
                                    <span>{{ $attendance->check_in_at?->format('H:i') ?: '—' }}–{{ $attendance->check_out_at?->format('H:i') ?: '—' }}</span>
                                    @if ($attendance->session?->topic)<br><span>{{ $attendance->session->topic }}</span>@endif
                                </td>
                                <td>
                                    <strong>{{ $teacherName }}</strong><br>
                                    <span>{{ $attendance->branch?->name ?: 'Cabang' }}</span>
                                </td>
                                <td><x-dashboard.status :status="$attendanceType !== 'present' ? $attendanceType : ($hasCheckOut ? 'completed' : ($hasCheckIn ? 'in_progress' : 'unrecorded'))" :label="$sessionStatus" /></td>
                                <td>
                                    <x-dashboard.status :status="$attendanceType !== 'present' ? $attendanceType : $attendance->status" :label="$meta['label']" />
                                    @if ($attendanceType === 'present' && $attendance->status === 'approved')
                                        <x-dashboard.approval :user="$attendance->reviewedBy" :at="$attendance->reviewed_at" :timezone="$attendance->branch?->timezone_name" />
                                    @endif
                                </td>
                                <td>
                                    <span class="dpe-dashboard-attendance-location-summary">
                                        @if ($attendanceType !== 'present')
                                            <span>Catatan manual Admin</span>
                                            <span>{{ $attendance->manual_note ?: 'Tanpa catatan tambahan' }}</span>
                                        @else
                                            <span>{{ $attendance->check_in_location_status === 'captured' ? 'Masuk: GPS' : 'Masuk: pengecualian' }}</span>
                                            <span>{{ $attendance->check_out_location_status === 'captured' ? 'Selesai: GPS' : ($attendance->check_out_at ? 'Selesai: pengecualian' : 'Belum ada') }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('admin.teacher-attendance.show', $attendance) }}">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$attendances" />
        @endif
    </article>
@endsection
