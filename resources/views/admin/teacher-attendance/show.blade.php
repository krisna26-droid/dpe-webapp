@extends('layouts.dashboard')

@php
    $hasCheckIn = $attendance->check_in_at !== null;
    $hasCheckOut = $attendance->check_out_at !== null;
    $attendanceType = $attendance->attendance_type ?: 'present';
    $manualException = $attendanceType !== 'present';
    $attendanceTypeMeta = [
        'present' => ['label' => 'Hadir'],
        'absent' => ['label' => 'Tidak hadir'],
        'leave' => ['label' => 'Izin'],
        'sick' => ['label' => 'Sakit'],
    ];
    $sessionStatus = $manualException
        ? ($attendanceTypeMeta[$attendanceType]['label'] ?? 'Ketidakhadiran')
        : (! $hasCheckIn
            ? 'Belum absen masuk'
            : ($hasCheckOut ? 'Selesai mengajar' : 'Sedang mengajar'));
    $validationMeta = $manualException
        ? ($attendanceTypeMeta[$attendanceType] ?? ['label' => 'Ketidakhadiran'])
        : ([
            'in_progress' => ['label' => 'Sedang mengajar'],
            'pending_review' => ['label' => 'Menunggu pemeriksaan'],
            'approved' => ['label' => 'Disetujui'],
            'needs_correction' => ['label' => 'Perlu koreksi'],
            'recorded' => ['label' => 'Dicatat Admin'],
        ][$attendance->status] ?? ['label' => ucfirst((string) $attendance->status)]);
    $teacherName = $attendance->teacher?->user?->full_name ?: ($attendance->teacher?->user?->username ?: 'Guru');
@endphp

@section('title', 'Detail Absensi Guru')
@section('heading', 'Detail Absensi Guru')
@section('description', 'Periksa sesi, bukti foto dan lokasi, lalu simpan hasil validasi absensi.')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/attendance-location.css') }}?v={{ filemtime(public_path('assets/dpe/attendance-location.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/dpe/attendance-location.js') }}?v={{ filemtime(public_path('assets/dpe/attendance-location.js')) }}" defer></script>
@endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Monitoring / Detail absensi</p>
            <h2>{{ $teacherName }}</h2>
            <p>{{ $attendance->recordDate()?->format('d M Y') ?: '—' }} · {{ $attendance->modeLabel() }} · {{ $attendance->branch?->name ?: 'Cabang belum tersedia' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <x-dashboard.status :status="$manualException ? $attendanceType : $attendance->status" :label="$validationMeta['label']" />
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.teacher-attendance.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke monitoring</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--two">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Informasi kehadiran</h2>
                    <p>{{ $attendance->modeLabel() }}</p>
                </div>
                <x-dashboard.status :status="$manualException ? $attendanceType : ($hasCheckOut ? 'completed' : ($hasCheckIn ? 'in_progress' : 'unrecorded'))" :label="$sessionStatus" />
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Cabang</p>
                        <p class="dpe-dashboard-detail__value">{{ $attendance->branch?->name ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Guru</p>
                        <p class="dpe-dashboard-detail__value">{{ $teacherName }}</p>
                    </div>
                    @if ($attendance->session)
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Program</p>
                        <p class="dpe-dashboard-detail__value">{{ $attendance->session?->program?->name ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Kelompok kelas</p>
                        <p class="dpe-dashboard-detail__value">{{ $attendance->session?->classGroup?->name ?: 'Kelas individual' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Mulai rencana</p>
                        <p class="dpe-dashboard-detail__value">{{ $attendance->session?->planned_start_at?->format('d M Y H:i') ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Selesai rencana</p>
                        <p class="dpe-dashboard-detail__value">{{ $attendance->session?->planned_end_at?->format('d M Y H:i') ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Topik / aktivitas</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ collect([$attendance->session?->topic, $attendance->session?->activity])->filter()->join(' · ') ?: 'Belum diisi' }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Waktu absensi</h2>
                    <p>{{ $manualException ? 'Sesi ditutup dengan status manual oleh Admin.' : 'Catatan aktual dari perangkat guru.' }}</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="clock" /> Aktual</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Absen masuk</p>
                        <p class="dpe-dashboard-detail__value">{{ $attendance->check_in_at?->format('d M Y H:i:s') ?: ($manualException ? 'Tidak dicatat' : 'Belum tercatat') }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Absen selesai</p>
                        <p class="dpe-dashboard-detail__value">{{ $attendance->check_out_at?->format('d M Y H:i:s') ?: ($manualException ? 'Tidak dicatat' : 'Belum tercatat') }}</p>
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--location">
                        <p class="dpe-dashboard-detail__label">Lokasi masuk</p>
                        <x-dashboard.attendance-location :point="$checkInLocation" :lookup-url="$checkInLocation ? route('admin.teacher-attendance.location', [$attendance, 'check-in']) : null" :empty-message="$manualException ? 'Tidak berlaku' : (! $hasCheckIn ? 'Belum tercatat' : ($attendance->check_in_location_status === 'captured' ? 'Koordinat tidak tersedia' : 'Pengecualian lokasi'))" />
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--location">
                        <p class="dpe-dashboard-detail__label">Lokasi selesai</p>
                        <x-dashboard.attendance-location :point="$checkOutLocation" :lookup-url="$checkOutLocation ? route('admin.teacher-attendance.location', [$attendance, 'check-out']) : null" :empty-message="$manualException ? 'Tidak berlaku' : (! $hasCheckOut ? 'Belum tercatat' : ($attendance->check_out_location_status === 'captured' ? 'Koordinat tidak tersedia' : 'Pengecualian lokasi'))" />
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Catatan lokasi</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">
                            {{ $manualException ? 'Tidak ada foto atau lokasi karena status dicatat manual.' : (collect([$attendance->check_in_location_note, $attendance->check_out_location_note])->filter()->join(' · ') ?: 'Tidak ada catatan pengecualian.') }}
                        </p>
                    </div>
                </div>
            </div>
        </article>
    </section>

    @if (! $manualException)
    <section class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Bukti absensi</h2>
                <p>Foto disimpan sebagai bukti pemeriksaan internal.</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="camera" /> Foto & lokasi</span>
        </header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-attendance-evidence-grid">
                <article class="dpe-dashboard-attendance-evidence">
                    <div class="dpe-dashboard-attendance-evidence__head">
                        <div>
                            <h3>Foto absen masuk</h3>
                            <p>{{ $attendance->check_in_at?->format('d M Y H:i:s') ?: 'Belum ada foto' }}</p>
                        </div>
                        @if ($attendance->check_in_photo_file_id)
                            <span class="dpe-dashboard-badge dpe-dashboard-badge--success">Tersedia</span>
                        @endif
                    </div>
                    @if ($attendance->check_in_photo_file_id)
                        <img class="dpe-dashboard-attendance-evidence__image" src="{{ route('admin.teacher-attendance.photo', [$attendance, 'check-in']) }}" alt="Foto absen masuk {{ $teacherName }}" loading="lazy">
                    @else
                        <x-dashboard.empty title="Belum ada foto masuk" message="Foto akan tampil setelah guru mencatat absen masuk." icon="camera" />
                    @endif
                    @if ($attendance->check_in_latitude !== null && $attendance->check_in_longitude !== null)
                        <p class="dpe-dashboard-attendance-coordinates">{{ $attendance->check_in_latitude }}, {{ $attendance->check_in_longitude }} · akurasi {{ $attendance->check_in_accuracy_m ?: '—' }} m</p>
                    @endif
                </article>

                <article class="dpe-dashboard-attendance-evidence">
                    <div class="dpe-dashboard-attendance-evidence__head">
                        <div>
                            <h3>Foto absen selesai</h3>
                            <p>{{ $attendance->check_out_at?->format('d M Y H:i:s') ?: 'Belum ada foto' }}</p>
                        </div>
                        @if ($attendance->check_out_photo_file_id)
                            <span class="dpe-dashboard-badge dpe-dashboard-badge--success">Tersedia</span>
                        @endif
                    </div>
                    @if ($attendance->check_out_photo_file_id)
                        <img class="dpe-dashboard-attendance-evidence__image" src="{{ route('admin.teacher-attendance.photo', [$attendance, 'check-out']) }}" alt="Foto absen selesai {{ $teacherName }}" loading="lazy">
                    @else
                        <x-dashboard.empty title="Belum ada foto selesai" message="Foto akan tampil setelah guru menutup sesi." icon="camera" />
                    @endif
                    @if ($attendance->check_out_latitude !== null && $attendance->check_out_longitude !== null)
                        <p class="dpe-dashboard-attendance-coordinates">{{ $attendance->check_out_latitude }}, {{ $attendance->check_out_longitude }} · akurasi {{ $attendance->check_out_accuracy_m ?: '—' }} m</p>
                    @endif
                </article>
            </div>
        </div>
    </section>

    <section class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Pemeriksaan absensi</h2>
                <p>Setujui bukti yang sesuai atau minta koreksi dengan alasan yang bisa ditindaklanjuti guru.</p>
            </div>
            <x-dashboard.status :status="$manualException ? $attendanceType : $attendance->status" :label="$validationMeta['label']" />
        </header>
        <div class="dpe-dashboard-card__body">
            @if ($attendance->status === 'pending_review')
                <form method="POST" action="{{ route('admin.teacher-attendance.review', $attendance) }}" class="dpe-dashboard-review-form">
                    @csrf
                    @method('PATCH')
                    <div class="dpe-dashboard-field">
                        <label for="review_note">Catatan pemeriksaan <span>(wajib saat meminta koreksi)</span></label>
                        <textarea id="review_note" name="review_note" rows="4" maxlength="1000" placeholder="Tuliskan hasil pemeriksaan atau alasan koreksi yang perlu ditindaklanjuti.">{{ old('review_note') }}</textarea>
                        @error('review_note')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                    </div>
                    @error('status')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                    <div class="dpe-dashboard-form-actions dpe-dashboard-review-actions">
                        <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit" name="status" value="needs_correction">
                            <x-dashboard.icon name="edit" />
                            <span>Minta koreksi</span>
                        </button>
                        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" name="status" value="approved">
                            <x-dashboard.icon name="check" />
                            <span>Setujui absensi</span>
                        </button>
                    </div>
                </form>
            @elseif ($attendance->status === 'in_progress')
                <x-dashboard.empty
                    title="Belum siap diperiksa"
                    message="Guru belum mencatat absen selesai. Pemeriksaan baru tersedia setelah sesi ditutup."
                    icon="clock"
                />
            @else
                <div class="dpe-dashboard-review-result {{ $attendance->status === 'approved' ? 'is-approved' : 'is-correction' }}">
                    <x-dashboard.icon name="{{ $attendance->status === 'approved' ? 'check' : 'edit' }}" />
                    <div>
                        <strong>{{ $validationMeta['label'] }}</strong>
                        <span>{{ $attendance->reviewedBy?->full_name ?: ($attendance->reviewedBy?->username ?: 'Petugas') }} · {{ $attendance->reviewed_at?->format('d M Y H:i') ?: 'Waktu tidak tersedia' }}</span>
                        @if ($attendance->review_note)<p>{{ $attendance->review_note }}</p>@endif
                    </div>
                </div>
            @endif
        </div>
    </section>
    @else
    <section class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Pencatatan ketidakhadiran</h2>
                <p>Status ini dicatat oleh Admin setelah sesi dimulai tanpa absensi guru.</p>
            </div>
            <x-dashboard.status :status="$manualException ? $attendanceType : $attendance->status"><x-dashboard.icon name="warning" /> {{ $validationMeta['label'] }}</x-dashboard.status>
        </header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Status kehadiran</p>
                    <p class="dpe-dashboard-detail__value">{{ $validationMeta['label'] }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Dicatat oleh</p>
                    <p class="dpe-dashboard-detail__value">{{ $attendance->recordedBy?->full_name ?: ($attendance->recordedBy?->username ?: 'Petugas') }}</p>
                </div>
                <div class="dpe-dashboard-detail">
                    <p class="dpe-dashboard-detail__label">Waktu pencatatan</p>
                    <p class="dpe-dashboard-detail__value">{{ $attendance->recorded_at?->format('d M Y H:i') ?: '—' }}</p>
                </div>
                <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                    <p class="dpe-dashboard-detail__label">Catatan</p>
                    <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $attendance->manual_note ?: 'Tidak ada catatan tambahan.' }}</p>
                </div>
            </div>
        </div>
    </section>
    @endif
    <section class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Riwayat tindakan</h2><p>Operator, waktu, dan alasan setiap pencatatan atau pemeriksaan mulai pembaruan ini.</p></div></header>
        @if ($auditLogs->isEmpty())
            <x-dashboard.empty title="Catatan dibuat sebelum riwayat tindakan tersedia" message="Data absensi lama tetap tersimpan. Tindakan berikutnya akan dicatat di sini." icon="clock" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table"><thead><tr><th>Waktu</th><th>Tindakan</th><th>Operator</th><th>Alasan</th></tr></thead><tbody>
                @foreach ($auditLogs as $log)<tr><td>{{ \Illuminate\Support\Carbon::parse($log->happened_at)->format('d M Y H:i:s') }}</td><td>{{ match ($log->event_type) { 'arrived' => 'Absen datang', 'departed' => 'Absen pulang', 'started' => 'Absen mulai', 'finished' => 'Absen selesai', 'approved' => 'Disetujui', 'needs_correction' => 'Koreksi diminta', 'manual_exception' => 'Ketidakhadiran dicatat', default => $log->event_type } }}</td><td>{{ $log->actor_name ?: '—' }}</td><td>{{ $log->reason ?: '—' }}</td></tr>@endforeach
            </tbody></table></div>
        @endif
    </section>
@endsection
