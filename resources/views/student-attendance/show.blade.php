@extends('layouts.dashboard')

@section('title', 'Absensi Pertemuan')
@section('heading', 'Absensi Pertemuan')
@section('description', 'Daftar peserta dan kehadiran pada satu pertemuan.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/student-attendance.css') }}?v={{ filemtime(public_path('assets/dpe/student-attendance.css')) }}">
@endpush
@push('scripts')
    <script src="{{ asset('assets/dpe/student-attendance.js') }}?v={{ filemtime(public_path('assets/dpe/student-attendance.js')) }}" defer></script>
@endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Pembelajaran / Pertemuan</p><h2>{{ $lessonSession->classGroup?->name ?: 'Private / tanpa kelompok' }}</h2><p>{{ $lessonSession->program?->name }} · {{ $lessonSession->branch?->name }} · {{ $meetingAt->locale('id')->translatedFormat('d F Y, H:i') }}</p></div>
        <div class="dpe-dashboard-page-header__actions">
            @if ($canAddMakeup)
                <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="#makeup-participant"><x-dashboard.icon name="plus" />Tambah siswa susulan</a>
            @elseif ($routeBase === 'superadmin.student-attendance' && $lessonSession->class_group_id)
                <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.class-group-memberships.create', ['class_group_id' => $lessonSession->class_group_id]) }}"><x-dashboard.icon name="plus" />Tambah anggota kelas</a>
            @elseif ($canManageRoster)
                <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="#attendance-roster"><x-dashboard.icon name="users" />Kelola peserta private</a>
            @endif
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index', ['period' => $meetingAt->format('Y-m'), 'class_group_id' => $lessonSession->class_group_id ?: 'private']) }}"><x-dashboard.icon name="back" />Kembali ke rekap</a>
        </div>
    </section>
    @if ($lessonSession->rescheduled_from_session_id)
        <p class="dpe-student-attendance-notice">Pertemuan ini merupakan jadwal pengganti. Kehadiran dihitung pada tanggal pelaksanaan ini.</p>
    @endif
    @if ($isSuperseded)
        <p class="dpe-student-attendance-notice">Sesi ini sudah memiliki jadwal pengganti. Kehadiran Hadir/Terlambat dicatat pada sesi penggantinya.</p>
    @endif
    @if ($canAddMakeup)
        <article class="dpe-dashboard-card dpe-student-attendance-roster" id="makeup-participant">
            <header class="dpe-dashboard-card__head"><div><h2>Tambah siswa susulan</h2><p>Pilih siswa aktif yang terdaftar di cabang {{ $lessonSession->branch?->name }} pada tanggal {{ $meetingAt->format('d/m/Y') }}. Peserta hanya ditambahkan ke pertemuan ini.</p></div></header>
            <div class="dpe-dashboard-card__body">
                @error('student_id')<p class="dpe-dashboard-alert dpe-dashboard-alert--error">{{ $message }}</p>@enderror
                @if ($makeupCandidates->isNotEmpty())
                    <form method="POST" action="{{ route('teacher.student-attendance.makeup.add', $lessonSession) }}" class="dpe-dashboard-form">
                        @csrf
                        <input type="hidden" name="expected_roster" value="{{ $rosterSignature }}">
                        <div class="dpe-dashboard-form-grid">
                            <div class="dpe-dashboard-field dpe-dashboard-field--full"><label for="makeup-student">Siswa</label><select id="makeup-student" name="student_id" required><option value="">Pilih siswa dari cabang ini</option>@foreach ($makeupCandidates as $candidate)<option value="{{ $candidate->id }}" @selected(old('student_id') === $candidate->id)>{{ $candidate->full_name }}</option>@endforeach</select></div>
                        </div>
                        <div class="dpe-dashboard-form-actions"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary"><x-dashboard.icon name="plus" />Tambahkan ke pertemuan</button></div>
                    </form>
                @else
                    <p>Semua siswa aktif di cabang ini yang memenuhi syarat sudah ada di peserta sesi, atau belum ada siswa lain yang memenuhi syarat.</p>
                @endif
            </div>
        </article>
    @endif
    @if ($students->isEmpty())
        @include('student-attendance._participant-help', ['classGroupId' => $lessonSession->class_group_id])
    @endif
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Peserta pertemuan</h2><p>Guru: {{ $lessonSession->teacher?->user?->full_name ?: $lessonSession->teacher?->user?->username }}</p></div><span class="dpe-dashboard-badge">{{ $students->count() }} siswa</span></header>
        @if ($students->isEmpty())
            <x-dashboard.empty title="Belum ada peserta pada sesi ini" message="Untuk kelas, periksa keanggotaan dan pendaftaran siswa. Untuk private, Admin atau Super Admin memilih peserta di halaman ini." icon="student" />
        @else
            <p class="dpe-student-attendance-help">Klik checkbox di sebelah nama siswa untuk membuka konfirmasi. Pilih status lalu tekan Ya, simpan; kehadiran diperbarui tanpa memuat ulang halaman. Perubahan catatan yang sudah tersimpan memerlukan alasan koreksi.</p>
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-student-attendance-session-table"><thead><tr><th>Siswa</th><th>Hadir</th><th>Mulai les</th><th>Status</th><th>Dicatat pertama</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($students as $student)
                    @php
                        $cell = $cells[$student->id];
                        $editUrl = route($routeBase.'.edit', [$lessonSession, $student]);
                    @endphp
                    <tr data-attendance-cell-view>
                        <td><strong>{{ $student->full_name }}</strong>@if ($makeupStudentIds->contains($student->id))<small>Siswa susulan</small>@endif</td>
                        <td class="dpe-student-attendance-check-cell">@include('student-attendance._quick', ['attendanceSession' => $lessonSession])</td>
                        <td>{{ $student->began_on->format('d M Y') }}</td>
                        <td><span class="dpe-student-attendance-status dpe-student-attendance-status--{{ array_key_exists($cell['status'] ?? '', $statuses) ? $cell['status'] : 'empty' }}" data-attendance-status>{{ $cell['label'] }}</span></td>
                        <td data-attendance-recorded-at>{{ $cell['record']?->recorded_at?->format('d M Y H:i') ?: '—' }}</td>
                        <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ $editUrl }}" data-attendance-detail>{{ $cell['record']?->attendance_status ? 'Ubah / riwayat' : 'Catat / detail' }}</a></td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </article>

    @if ($lessonSession->class_group_id)
        <p class="dpe-student-attendance-help">Peserta kelas mengikuti keanggotaan, pendaftaran program, dan tanggal mulai les pada tanggal pertemuan. Catatan yang sudah tersimpan tetap dapat ditelusuri.</p>
    @elseif ($canManageRoster)
        <section class="dpe-student-attendance-roster" id="attendance-roster">
            <h3>Kelola peserta private</h3><p class="dpe-student-attendance-help">Pilih siswa yang mengikuti pertemuan ini. Peserta dengan catatan tersimpan tetap dipertahankan.</p>
            <form method="POST" action="{{ route($routeBase.'.roster', $lessonSession) }}" class="dpe-dashboard-form">
                @csrf @method('PUT')
                <input type="hidden" name="expected_roster" value="{{ $rosterSignature }}">
                <div class="dpe-student-attendance-roster-options">
                    @forelse ($candidates as $candidate)
                        @php
                            $record = $lessonSession->sessionStudents->firstWhere('student_id', $candidate->id);
                            $protected = $record && ($record->attendance_status !== null || $record->recorded_at !== null || $record->individual_learning_note !== null || $record->attendance_updated_at !== null);
                        @endphp
                        @if ($protected)<input type="hidden" name="student_ids[]" value="{{ $candidate->id }}">@endif
                        <label class="dpe-dashboard-checkbox"><input type="checkbox" name="student_ids[]" value="{{ $candidate->id }}" @checked(in_array($candidate->id, old('student_ids', $students->pluck('id')->all()), true)) @disabled($protected)><span>{{ $candidate->full_name }}<small>Mulai {{ $candidate->began_on->format('d M Y') }}{{ $protected ? ' · Memiliki catatan' : '' }}</small></span></label>
                    @empty
                        <p>Belum ada siswa terdaftar pada cabang/program untuk tanggal pertemuan ini.</p>
                    @endforelse
                </div>
                @error('student_ids')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                <div class="dpe-dashboard-form-actions"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Simpan peserta</button></div>
            </form>
        </section>
    @else
        <p class="dpe-student-attendance-help">Daftar peserta private ditetapkan oleh Admin atau Super Admin.</p>
    @endif
    @include('student-attendance._confirm')
@endsection
