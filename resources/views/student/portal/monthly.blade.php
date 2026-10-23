@extends('layouts.dashboard')
@section('title', 'Laporan Bulanan')
@section('heading', 'Laporan Bulanan')
@section('description', 'Salinan isi yang sudah disetujui untuk orang tua.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    @include('student.portal._nav', ['back' => route('student.reports.index'), 'backLabel' => 'Kembali ke Rapor Siswa'])
    <article class="dpe-dashboard-card dpe-portal-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>{{ $period }} · {{ $public['student_name'] }}</h2><p>{{ $public['branch_name'] }} · {{ $public['teacher_name'] }}</p></div><span class="dpe-dashboard-badge dpe-dashboard-badge--success">Disetujui</span></header>
        <div class="dpe-dashboard-card__body"><h3>Ringkasan perkembangan</h3><p class="dpe-portal-text">{{ $public['development_summary'] }}</p></div>
        <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-portal-skills"><thead><tr><th>Skill</th><th>Perkembangan</th><th>Deskripsi</th></tr></thead><tbody>
            @foreach ($public['skills'] as $skill)
                <tr><td><strong>{{ $skill['name'] }}</strong></td><td><span class="dpe-dashboard-badge">{{ $skill['trend'] }}</span></td><td class="dpe-portal-text">{{ $skill['description'] }}</td></tr>
            @endforeach
        </tbody></table></div>
        <div class="dpe-dashboard-card__body"><h3>Challenges</h3><p class="dpe-portal-text">{{ $public['parent_challenges_summary'] }}</p><h3>Teacher's Message</h3><p class="dpe-portal-text">{{ $public['parent_message'] }}</p></div>
    </article>
    @if ($attendance !== null)
        <article class="dpe-dashboard-card dpe-portal-spaced dpe-portal-attendance">
            <header class="dpe-dashboard-card__head"><div><h2>Kehadiran siswa</h2><p>Rekap terkini untuk cabang dan bulan laporan ini.</p></div></header>
            <div class="dpe-dashboard-card__body">
                <p>Total hadir: {{ $attendance['attended_count'] }} dari {{ $attendance['meeting_count'] }} pertemuan berlangsung.</p>
                <p>Hadir tepat waktu {{ $attendance['present'] }} · Terlambat {{ $attendance['late'] }} · Tidak Hadir {{ $attendance['absent'] }} · Sakit {{ $attendance['sick'] }} · Izin {{ $attendance['permission'] }} · Dijadwalkan Ulang {{ $attendance['rescheduled'] }}</p>
                <p>Belum dicatat: {{ $attendance['unrecorded'] }}. Kotak yang belum diisi tidak dianggap tidak hadir.</p>
            </div>
        </article>
    @endif
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Video pembelajaran</h2><p>{{ count($public['videos']) }} video · target {{ $public['video_target'] }}</p></div></header>
        <div class="dpe-dashboard-card__body">@include('student.portal._videos', ['videos' => $public['videos']])</div>
    </article>
@endsection

