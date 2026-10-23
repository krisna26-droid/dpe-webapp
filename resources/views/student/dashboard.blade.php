@extends('layouts.dashboard')
@section('title', 'Dashboard Siswa')
@section('heading', 'Dashboard Anak')
@section('description', 'Jadwal, pembayaran, dan laporan untuk satu siswa.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Perjalanan belajar</p>
            <h2>{{ $profile['name'] }}</h2>
            <p>Informasi belajar anak dan laporan yang tersedia.</p>
        </div>
        <div class="dpe-portal-actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('student.profile') }}">Profil Siswa</a>
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('student.payments.index') }}">Pembayaran</a>
        </div>
    </section>
    <section class="dpe-dashboard-grid dpe-dashboard-grid--metrics dpe-portal-metrics dpe-portal-spaced">
        <x-dashboard.metric-card label="Mulai les" :value="$profile['began_on']->format('d M Y')" meta="Tanggal bergabung siswa" icon="calendar" />
        <x-dashboard.metric-card label="Pertemuan bulan ini" :value="$meetingProgress['value']" :meta="$meetingProgress['meta']" icon="calendar" tone="green" />
        <x-dashboard.metric-card label="Laporan tersedia" :value="$available->count()" meta="Di antara enam laporan terbaru" icon="report" tone="orange" />
        <x-dashboard.metric-card label="Pembayaran terbaru" :value="$payments->first()['state']['label'] ?? 'Belum ada'" meta="Status periode pembayaran terakhir" icon="wallet" tone="purple" />
    </section>
    <section class="dpe-portal-grid dpe-portal-spaced">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Jadwal terdekat</h2><p>Maksimal empat jadwal dalam dua bulan ini.</p></div><a href="{{ route('student.schedule') }}">Lihat Jadwal</a></header>
            <div class="dpe-dashboard-card__body">
                @forelse ($upcoming as $session)
                    <div class="dpe-portal-item">
                        <strong>{{ $session['date']->locale('id')->translatedFormat('l, d F Y · H:i') }}</strong>
                        <p>{{ $session['program_name'] }} · {{ $session['teacher_name'] }}</p>
                        <small>{{ $session['branch_name'] }} · {{ $session['location'] }}</small>
                    </div>
                @empty
                    <p>Belum ada jadwal terdekat untuk siswa ini.</p>
                @endforelse
            </div>
        </article>
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Pesan Teacher terbaru</h2><p>Dari laporan bulanan yang sudah terbuka.</p></div></header>
            <div class="dpe-dashboard-card__body">
                <p class="dpe-portal-text">{{ $message ?: 'Pesan akan tampil setelah laporan bulanan disetujui dan pembayaran bulan tersebut tervalidasi.' }}</p>
                <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('student.reports.index') }}">Buka Rapor Siswa</a>
            </div>
        </article>
    </section>
    <article class="dpe-dashboard-card dpe-portal-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Laporan bulanan terbaru</h2><p>Setiap bulan memiliki syarat akses sendiri.</p></div></header>
        <div class="dpe-dashboard-card__body">
            @forelse ($reports as $row)
                <div class="dpe-portal-item dpe-portal-row">
                    <div><strong>{{ $row['report']->report_month->locale('id')->translatedFormat('F Y') }}</strong><small>{{ $row['state']['ready'] ? 'Tersedia' : implode(' ', $row['state']['reasons']) }}</small></div>
                    <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('student.reports.monthly', $row['report']) }}">{{ $row['state']['ready'] ? 'Lihat Laporan' : 'Lihat Syarat' }}</a>
                </div>
            @empty
                <p>Admin belum menyiapkan periode bulanan untuk siswa ini.</p>
            @endforelse
        </div>
    </article>
@endsection

