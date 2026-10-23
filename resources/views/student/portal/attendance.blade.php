@extends('layouts.dashboard')
@section('title', 'Absensi Tiga Bulan')
@section('heading', 'Absensi Tiga Bulan')
@section('description', 'Riwayat kehadiran siswa pada tiga bulan pilihan Anda.')
@push('head')
    @include('student.portal._head')
@endpush
@push('scripts')
    <script src="{{ asset('assets/dpe/student-portal-attendance.js') }}?v={{ filemtime(public_path('assets/dpe/student-portal-attendance.js')) }}" defer></script>
@endpush
@section('content')
    @include('student.portal._nav')
    <article class="dpe-dashboard-card dpe-portal-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Pilih tiga bulan</h2><p id="attendance-month-help">Setiap bulan dapat dipilih sendiri. Daftar langsung diperbarui saat pilihan berubah.</p></div></header>
        <div class="dpe-dashboard-card__body">
            <form method="GET" action="{{ route('student.attendance') }}" class="dpe-portal-month-choices" data-portal-attendance-months aria-describedby="attendance-month-help">
                @foreach ($months as $index => $month)
                    <div class="dpe-dashboard-field">
                        <label for="attendance-month-{{ $index }}">Bulan {{ $index + 1 }}</label>
                        <input id="attendance-month-{{ $index }}" type="month" name="months[]" value="{{ $month }}" min="1900-01" max="9999-12" required data-attendance-month>
                    </div>
                @endforeach
                <noscript><p>Aktifkan JavaScript pada browser untuk mengubah pilihan bulan.</p></noscript>
            </form>
            <p class="dpe-portal-muted dpe-portal-attendance-hint">Absensi ditampilkan sesuai pengaturan cabang. Jadwal yang belum berlangsung tidak masuk ke riwayat ini.</p>
        </div>
    </article>
    <div class="dpe-portal-stack">
        @foreach ($attendanceMonths as $row)
            <article class="dpe-dashboard-card" data-attendance-period="{{ $row['month'] }}">
                <header class="dpe-dashboard-card__head"><div><h2>{{ $row['label'] }}</h2><p>Seluruh catatan pertemuan yang tersedia pada bulan ini.</p></div></header>
                @if ($row['sessions']->isNotEmpty())
                    <div class="dpe-dashboard-card__body dpe-portal-attendance-summary">
                        <p><strong>Total hadir: {{ $row['summary']['attended_count'] }} dari {{ $row['summary']['meeting_count'] }} pertemuan berlangsung.</strong></p>
                        <dl>
                            @foreach (['present' => 'Hadir tepat waktu', 'late' => 'Terlambat', 'absent' => 'Tidak hadir', 'sick' => 'Sakit', 'permission' => 'Izin', 'rescheduled' => 'Dijadwalkan ulang', 'unrecorded' => 'Belum dicatat'] as $key => $label)
                                <div><dt>{{ $label }}</dt><dd>{{ $row['summary'][$key] }}</dd></div>
                            @endforeach
                            @if ($row['summary']['unknown'])<div><dt>Status lama lainnya</dt><dd>{{ $row['summary']['unknown'] }}</dd></div>@endif
                        </dl>
                    </div>
                    <div class="dpe-dashboard-table-wrap" tabindex="0" role="region" aria-label="Daftar absensi {{ $row['label'] }}">
                        <table class="dpe-dashboard-table dpe-portal-attendance-table">
                            <thead><tr><th scope="col">No.</th><th scope="col">Tanggal & jam</th><th scope="col">Cabang / program</th><th scope="col">Teacher</th><th scope="col">Kehadiran</th></tr></thead>
                            <tbody>
                                @foreach ($row['sessions'] as $session)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td><time datetime="{{ $session['date']->toIso8601String() }}"><strong>{{ $session['date']->locale('id')->translatedFormat('d F Y') }}</strong><small>{{ $session['date']->format('H:i') }}</small></time></td>
                                        <td><strong>{{ $session['branch_name'] }}</strong><small>{{ $session['program_name'] }}{{ $session['class_name'] ? ' / '.$session['class_name'] : '' }}</small><small>{{ $session['location'] }}</small></td>
                                        <td>{{ $session['teacher_name'] ?: 'Belum dipilih' }}</td>
                                        <td><x-dashboard.status :status="$session['attendance_label']" :label="$session['attendance_label']" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="dpe-dashboard-card__body"><p class="dpe-portal-muted dpe-portal-attendance-hint">Belum dicatat tidak dianggap tidak hadir. Sesi dibatalkan dan jadwal yang diganti tidak dihitung sebagai pertemuan berlangsung.</p></div>
                @else
                    <div class="dpe-dashboard-card__body"><x-dashboard.empty title="Belum ada absensi yang dapat ditampilkan" message="Catatan muncul setelah pertemuan berlangsung dan tampilan absensi diaktifkan oleh cabang." icon="calendar" /></div>
                @endif
            </article>
        @endforeach
    </div>
@endsection
