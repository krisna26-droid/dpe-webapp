@extends('layouts.dashboard')

@section('title', 'Catat Kehadiran Siswa')
@section('heading', 'Catat Kehadiran Siswa')
@section('description', 'Status kehadiran, catatan, dan riwayat koreksi siswa pada pertemuan ini.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/student-attendance.css') }}?v={{ filemtime(public_path('assets/dpe/student-attendance.css')) }}">
@endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Pembelajaran / Kehadiran siswa</p><h2>{{ $student->full_name }}</h2><p>{{ $lessonSession->branch?->name }} · {{ $lessonSession->program?->name }} · Pertemuan {{ $cell['date']->locale('id')->translatedFormat('d F Y, H:i') }}</p></div>
        <div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.show', $lessonSession) }}"><x-dashboard.icon name="back" />Kembali ke peserta</a></div>
    </section>
    <div class="dpe-student-attendance-detail-meta"><p><span>Mulai les</span><strong>{{ $student->began_on->format('d M Y') }}</strong></p><p><span>Status saat ini</span><strong>{{ $cell['label'] }}</strong></p><p><span>Dicatat pertama</span><strong>{{ $cell['record']?->recorded_at?->format('d M Y H:i') ?: ($cell['requires_reason'] ? 'Waktu lama tidak tersedia' : 'Belum dicatat') }}</strong></p><p><span>Perubahan terakhir</span><strong>{{ $cell['record']?->attendance_updated_at?->format('d M Y H:i') ?: '—' }}</strong></p></div>
    @if ($cell['reason'])<p class="dpe-student-attendance-notice">{{ $cell['reason'] }}. Kehadiran baru mengikuti tanggal pertemuan yang berlaku.</p>@endif
    <form method="POST" action="{{ route($routeBase.'.save', [$lessonSession, $student]) }}" class="dpe-dashboard-form">
        @csrf
        <input type="hidden" name="expected_version" value="{{ old('expected_version', $cell['version']) }}">
        <div class="dpe-dashboard-form-grid">
            <div class="dpe-dashboard-field">
                <label for="student-status">Status kehadiran</label>
                <select name="attendance_status" id="student-status">
                    <option value="">Belum dicatat / kosongkan</option>
                    @if ($cell['record']?->attendance_status && ! array_key_exists($cell['record']->attendance_status, $statuses))
                        <option disabled selected>Status lama: {{ $cell['record']->attendance_status }} — pilih status</option>
                    @endif
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('attendance_status', $cell['record']?->attendance_status) === $value) @disabled($value === 'rescheduled' ? ! $cell['can_reschedule'] : ! $cell['can_record'])>{{ $label }}</option>
                    @endforeach
                </select>
                @error('attendance_status')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                <p class="dpe-dashboard-field__hint">Terlambat tetap dihitung hadir. Status kosong tidak dihitung Tidak Hadir.</p>
            </div>
            <div class="dpe-dashboard-field"><label for="student-note">Catatan pertemuan siswa</label><textarea name="individual_learning_note" id="student-note" maxlength="255" rows="3" placeholder="Keterangan singkat pertemuan">{{ old('individual_learning_note', $cell['record']?->individual_learning_note) }}</textarea>@error('individual_learning_note')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
            <div class="dpe-dashboard-field dpe-dashboard-field--full"><label for="student-correction">Alasan koreksi{{ $cell['requires_reason'] ? ' *' : ' (jika ada)' }}</label><textarea name="correction_reason" id="student-correction" maxlength="1000" rows="3" @required($cell['requires_reason']) placeholder="Jelaskan alasan perubahan status atau catatan">{{ old('correction_reason') }}</textarea>@error('correction_reason')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror<p class="dpe-dashboard-field__hint">Waktu pencatatan pertama tetap disimpan. Setiap koreksi menyimpan nilai sebelum/sesudah, alasan, dan operator.</p></div>
        </div>
        @error('expected_version')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        <div class="dpe-dashboard-form-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.show', $lessonSession) }}">Batal</a><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Simpan kehadiran</button></div>
    </form>

    <article class="dpe-dashboard-card dpe-student-attendance-history">
        <header class="dpe-dashboard-card__head"><div><h2>Riwayat pencatatan dan koreksi</h2><p>Tanggal pertemuan {{ $cell['date']->format('d M Y') }} tersimpan terpisah dari waktu pengisian.</p></div></header>
        @if ($history->isEmpty())
            <x-dashboard.empty title="Belum ada riwayat pembaruan" message="Catatan lama tetap disimpan. Operator dan riwayat sebelum fitur ini tersedia tidak direka ulang." icon="clock" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table"><thead><tr><th>Waktu / operator</th><th>Tindakan</th><th>Sebelum</th><th>Sesudah</th><th>Alasan</th></tr></thead><tbody>
                @foreach ($history as $event)
                    @php
                        $events = ['recorded' => 'Pencatatan', 'corrected' => 'Koreksi', 'participant_added' => 'Peserta ditambahkan', 'participant_removed' => 'Peserta dilepas'];
                        $before = $event->before_snapshot['attendance_status'] ?? null;
                        $after = $event->after_snapshot['attendance_status'] ?? null;
                    @endphp
                    <tr><td><strong>{{ $event->happened_at->format('d M Y H:i:s') }}</strong><br>{{ $event->actor?->full_name ?: 'Operator tidak tersedia' }}</td><td>{{ $events[$event->event_type] ?? $event->event_type }}</td><td>{{ $statuses[$before] ?? ($before ?: 'Belum dicatat') }}@if ($event->before_snapshot['individual_learning_note'] ?? null)<small class="dpe-student-attendance-audit-note">{{ $event->before_snapshot['individual_learning_note'] }}</small>@endif</td><td>{{ $statuses[$after] ?? ($after ?: 'Belum dicatat') }}@if ($event->after_snapshot['individual_learning_note'] ?? null)<small class="dpe-student-attendance-audit-note">{{ $event->after_snapshot['individual_learning_note'] }}</small>@endif</td><td>{{ $event->reason ?: '—' }}</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </article>
@endsection
