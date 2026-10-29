@extends('layouts.dashboard')
@section('title', $history ? 'Riwayat Belajar' : 'Jadwal Belajar')
@section('heading', $history ? 'Riwayat Belajar' : 'Jadwal Belajar')
@section('description', 'Pertemuan yang terhubung dengan pendaftaran siswa.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    @include('student.portal._nav')
    <article class="dpe-dashboard-card dpe-portal-spaced">
        <div class="dpe-dashboard-card__body">
            <form method="GET" class="dpe-portal-filter">
                <div class="dpe-dashboard-field"><label for="portal-month">Bulan</label><input id="portal-month" type="month" name="month" value="{{ $month }}" required></div>
                <div class="dpe-portal-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($history ? 'student.history' : 'student.schedule') }}">Reset</a></div>
            </form>
        </div>
    </article>
    <div class="dpe-portal-stack dpe-portal-spaced">
        @forelse ($sessions as $session)
            <article class="dpe-dashboard-card">
                <header class="dpe-dashboard-card__head"><div><h2>{{ $session['date']->locale('id')->translatedFormat('l, d F Y · H:i') }}</h2><p>{{ $session['branch_name'] }} · {{ $session['location'] }}</p></div><span class="dpe-dashboard-badge">{{ $session['label'] }}</span></header>
                <div class="dpe-dashboard-card__body">
                    <dl class="dpe-portal-details"><div><dt>Program / Kelas</dt><dd>{{ $session['program_name'] }}{{ $session['class_name'] ? ' / '.$session['class_name'] : '' }}{{ ($session['learning_session_name'] ?? null) ? ' · '.$session['learning_session_name'] : '' }}</dd></div><div><dt>Teacher</dt><dd>{{ $session['teacher_name'] ?: 'Belum dipilih' }}</dd></div></dl>
                    @if ($session['topic'])<p><strong>Topik:</strong> {{ $session['topic'] }}</p>@endif
                    @if ($history && ! $session['cancelled'] && ! $session['superseded'])
                        @if ($session['material'])<p class="dpe-portal-text"><strong>Materi:</strong> {{ $session['material'] }}</p>@endif
                        @if ($session['activity'])<p class="dpe-portal-text"><strong>Aktivitas:</strong> {{ $session['activity'] }}</p>@endif
                    @endif
                    @if (array_key_exists('attendance_label', $session))
                        <p class="dpe-portal-attendance"><strong>Kehadiran siswa:</strong> {{ $session['attendance_label'] }}</p>
                    @endif
                </div>
            </article>
        @empty
            <x-dashboard.empty title="Belum ada pertemuan pada bulan ini" message="Jadwal mengikuti peserta sesi, pendaftaran, keanggotaan kelas, dan tanggal mulai les siswa." icon="calendar" />
        @endforelse
    </div>
    @if ($history)
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Progres, video, dan pesan bulanan</h2><p>Isi ditampilkan dari laporan yang sudah disetujui dan pembayaran yang tervalidasi.</p></div></header>
            <div class="dpe-dashboard-card__body">
                @forelse ($publicReports as $row)
                    <div class="dpe-portal-item">
                        <p class="dpe-portal-text">{{ $row['public']['development_summary'] }}</p>
                        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('student.reports.monthly', $row['report']) }}">Lihat Progres, Challenges & Pesan</a>
                        @include('student.portal._videos', ['videos' => $row['public']['videos']])
                    </div>
                @empty
                    <p>Laporan bulanan belum terbuka. Periksa persetujuan dan pembayaran pada menu Rapor Siswa.</p>
                    <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('student.reports.index') }}">Periksa Rapor Siswa</a>
                @endforelse
            </div>
        </article>
    @endif
@endsection

