@php
    $attendanceBase = str_replace('learning-notes', 'student-attendance', $routeBase);
    $stale = $errors->has('expected_snapshot');
    $readonly = $blockReason !== null || $stale;
@endphp
@extends('layouts.dashboard')

@section('title', 'Catatan Pertemuan')
@section('heading', 'Catatan Pertemuan')
@section('description', 'Ringkasan belajar dan catatan individual pada satu pertemuan.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/lesson-learning.css') }}?v={{ filemtime(public_path('assets/dpe/lesson-learning.css')) }}">
@endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Catatan Belajar</p>
            <h2>{{ $lessonSession->classGroup?->name ?: 'Private / tanpa kelompok' }}</h2>
            <p>{{ $lessonSession->program?->name }} · {{ $lessonSession->branch?->name }} · {{ $meetingAt->locale('id')->translatedFormat('d F Y, H:i') }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($attendanceBase.'.show', $lessonSession) }}"><x-dashboard.icon name="student" />Buka absensi siswa</a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index', ['month' => $meetingAt->format('Y-m')]) }}"><x-dashboard.icon name="back" />Kembali ke Catatan Belajar</a>
        </div>
    </section>

    @if ($blockReason)
        <div class="dpe-lesson-learning-notice" role="status"><x-dashboard.icon name="info" /><p>{{ $blockReason }}</p></div>
    @endif
    @if ($stale)
        <div class="dpe-lesson-learning-notice" role="alert"><x-dashboard.icon name="warning" /><div><p>{{ $errors->first('expected_snapshot') }}</p><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.edit', $lessonSession) }}">Muat data terbaru</a></div></div>
    @endif
    @if ($lessonSession->rescheduled_from_session_id)
        <div class="dpe-lesson-learning-notice dpe-lesson-learning-notice--info"><x-dashboard.icon name="calendar" /><p>Pertemuan ini merupakan jadwal pengganti. Catatan mengikuti tanggal pelaksanaan pertemuan ini.</p></div>
    @endif

    <form method="POST" action="{{ route($routeBase.'.save', $lessonSession) }}" class="dpe-dashboard-form dpe-lesson-learning-form">
        @csrf @method('PUT')
        <input type="hidden" name="expected_snapshot" value="{{ $expectedSnapshot }}">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Ringkasan pertemuan</h2><p>Isi yang sudah tersedia dari jadwal ditampilkan di sini dan dapat dilanjutkan.</p></div><span class="dpe-dashboard-badge"><x-dashboard.icon name="book" />Pembelajaran</span></header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-lesson-learning-session-meta">
                    <span><strong>Guru</strong>{{ $lessonSession->teacher?->user?->full_name ?: $lessonSession->teacher?->user?->username }}</span>
                    <span><strong>Lokasi</strong>{{ $lessonSession->isAtStudentHome() ? 'Private di rumah siswa' : 'Di cabang' }}</span>
                    <span><strong>Peserta</strong>{{ $participants->count() }} siswa</span>
                </div>
                <div class="dpe-dashboard-form-grid dpe-dashboard-form-grid--three">
                    @foreach (\App\Services\LessonLearningService::FIELDS as $field => $label)
                        <div class="dpe-dashboard-field">
                            <label for="learning-{{ $field }}">{{ $label }}</label>
                            <textarea id="learning-{{ $field }}" name="{{ $field }}" rows="4" maxlength="255" aria-describedby="learning-limit" @readonly($readonly)>{{ old($field, $lessonSession->$field) }}</textarea>
                            @error($field)<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <p class="dpe-lesson-learning-help" id="learning-limit">Maksimal 255 karakter per kolom. Anda dapat menyimpan sebagian isian; ringkasan ditandai lengkap setelah ketiga kolom terisi.</p>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Catatan individual siswa</h2><p>Catatan internal untuk Teacher dan petugas yang berwenang. Catatan ini terpisah dari pesan publik untuk orang tua.</p></div><span class="dpe-dashboard-badge">{{ $participants->count() }} siswa</span></header>
            @if ($participants->isEmpty())
                <x-dashboard.empty title="Belum ada peserta pada pertemuan ini" message="Admin atau Super Admin perlu memeriksa anggota kelas atau memilih peserta private di menu Absensi Siswa." icon="student" />
            @else
                <div class="dpe-dashboard-table-wrap">
                    <table class="dpe-dashboard-table dpe-lesson-learning-students">
                        <thead><tr><th>Siswa</th><th>Status kehadiran</th><th>Mulai les</th><th>Catatan internal</th></tr></thead>
                        <tbody>
                            @foreach ($participants as $studentId => $row)
                                <tr>
                                    <td><strong>{{ $row['student']->full_name }}</strong></td>
                                    <td><a href="{{ route($attendanceBase.'.edit', [$lessonSession, $row['student']]) }}">{{ $row['cell']['label'] }}</a></td>
                                    <td>{{ $row['student']->began_on->format('d M Y') }}</td>
                                    <td>
                                        <div class="dpe-dashboard-field">
                                            <label class="dpe-lesson-learning-sr-only" for="learning-note-{{ $studentId }}">Catatan internal {{ $row['student']->full_name }}</label>
                                            <textarea id="learning-note-{{ $studentId }}" name="student_notes[{{ $studentId }}]" rows="3" maxlength="255" @disabled($readonly || ! $row['can_edit_note']) placeholder="Proses belajar, kendala, atau hal yang perlu ditindaklanjuti">{{ old('student_notes.'.$studentId, $row['cell']['record']?->individual_learning_note) }}</textarea>
                                            @error('student_notes.'.$studentId)<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                                            @if (! $row['can_edit_note'] && ! $blockReason)<p class="dpe-lesson-learning-help">{{ $row['cell']['reason'] ?: 'Periksa status lama pada detail absensi sebelum mengubah catatan.' }}</p>@endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <div class="dpe-dashboard-card__body"><p class="dpe-lesson-learning-help">Maksimal 255 karakter per siswa. Kehadiran dicatat melalui Buka absensi siswa; menyimpan catatan belajar mempertahankan status kehadiran yang sudah ada.</p></div>
        </article>

        @if (! $readonly)
            <article class="dpe-dashboard-card">
                <div class="dpe-dashboard-card__body">
                    <div class="dpe-dashboard-field">
                        <label for="learning-reason">Alasan perubahan <span>(wajib saat mengubah data yang sudah tersimpan)</span></label>
                        <textarea id="learning-reason" name="correction_reason" rows="2" maxlength="1000" placeholder="Contoh: Melengkapi evaluasi setelah memeriksa hasil latihan siswa">{{ old('correction_reason') }}</textarea>
                        @error('correction_reason')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="dpe-dashboard-form-actions">
                        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index', ['month' => $meetingAt->format('Y-m')]) }}">Batal</a>
                        <button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary"><x-dashboard.icon name="check" />Simpan catatan belajar</button>
                    </div>
                </div>
            </article>
        @endif
    </form>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced" id="learning-history">
        <header class="dpe-dashboard-card__head"><div><h2>Riwayat perubahan</h2><p>Pencatatan melalui halaman ini menyimpan operator, waktu, alasan, dan isi sebelum serta sesudah perubahan.</p></div><span class="dpe-dashboard-badge">{{ $history->total() }} perubahan</span></header>
        @if ($historyRows->isEmpty())
            <x-dashboard.empty title="Belum ada riwayat dari halaman Catatan Belajar" message="Isi yang sudah ada tetap ditampilkan. Riwayat perubahan lama tidak dibuat ulang." icon="book" />
        @else
            <div class="dpe-lesson-learning-history">
                @foreach ($historyRows as $historyRow)
                    <details class="dpe-lesson-learning-history-item">
                        <summary><span><strong>{{ $historyRow['event']->event_type === 'recorded' ? 'Catatan disimpan' : 'Catatan diperbarui' }}</strong><small>{{ $historyRow['event']->actor?->full_name ?: $historyRow['event']->actor?->username ?: 'Operator tidak tersedia' }} · {{ $historyRow['event']->happened_at->format('d M Y H:i') }}</small></span><span class="dpe-dashboard-badge">{{ count($historyRow['changes']) }} isian</span></summary>
                        <div class="dpe-lesson-learning-history-content">
                            @if ($historyRow['event']->reason)<p class="dpe-lesson-learning-history-reason"><strong>Alasan:</strong> {{ $historyRow['event']->reason }}</p>@endif
                            @foreach ($historyRow['changes'] as $change)
                                <div class="dpe-lesson-learning-change"><h3>{{ $change['label'] }}</h3><div><p><strong>Sebelum</strong><span>{{ $change['before'] === null || $change['before'] === '' ? 'Belum diisi' : $change['before'] }}</span></p><p><strong>Sesudah</strong><span>{{ $change['after'] === null || $change['after'] === '' ? 'Dikosongkan' : $change['after'] }}</span></p></div></div>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
            <x-dashboard.pagination :paginator="$history" />
        @endif
    </article>
@endsection
