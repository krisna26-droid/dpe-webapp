@extends('layouts.dashboard')
@section('title', $teacher ? 'Laporan Bulanan' : 'Tinjau Laporan Bulanan')
@section('heading', $teacher ? 'Laporan Bulanan' : 'Tinjau Laporan Bulanan')
@section('description', 'Pengajuan, revisi, dan persetujuan laporan siswa per bulan kalender.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/student-progress.css') }}?v={{ filemtime(public_path('assets/dpe/student-progress.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/dpe/monthly-reports.css') }}?v={{ filemtime(public_path('assets/dpe/monthly-reports.css')) }}">
@endpush
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Laporan / Bulanan</p>
            <h2>{{ $teacher ? 'Periksa dan ajukan laporan siswa.' : 'Periksa laporan yang diajukan Teacher.' }}</h2>
            <p>{{ $teacher ? 'Lengkapi perkembangan dan pesan, periksa laporan, lalu ajukan kepada Admin.' : 'Tinjau delapan kategori, video, serta pesan sebelum menyetujui atau meminta revisi.' }}</p>
        </div>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($progressBase.'.index', array_filter(['month' => $filters['month']])) }}"><x-dashboard.icon name="back" />Perkembangan &amp; Pesan</a>
    </section>
    <article class="dpe-dashboard-card dpe-progress-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Filter laporan</h2><p>{{ $teacher ? 'Daftar memuat laporan yang menetapkan Anda sebagai pemilik.' : 'Antrean awal menampilkan laporan Diajukan dari cabang yang dapat Anda tinjau.' }}</p></div></header>
        <div class="dpe-dashboard-card__body">
            <form method="GET" action="{{ route($routeBase.'.index') }}" class="dpe-dashboard-form">
                <div class="dpe-monthly-filter-grid">
                    <div class="dpe-dashboard-field"><label for="monthly-month">Bulan (opsional)</label><input id="monthly-month" name="month" type="month" value="{{ $filters['month'] }}"><p class="dpe-dashboard-field__hint">Kosongkan untuk semua bulan.</p></div>
                    <div class="dpe-dashboard-field"><label for="monthly-status">Status</label><select id="monthly-status" name="status"><option value="all" @selected(($filters['status'] ?? 'all') === 'all')>Semua status</option>@foreach ($statusLabels as $code => $label)<option value="{{ $code }}" @selected($filters['status'] === $code)>{{ $label }}</option>@endforeach</select></div>
                    <div class="dpe-dashboard-field"><label for="monthly-branch">Cabang</label><select id="monthly-branch" name="branch_id"><option value="">Semua cabang saya</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="dpe-dashboard-field"><label for="monthly-search">Nama siswa</label><input id="monthly-search" name="q" type="search" maxlength="100" value="{{ $filters['q'] }}" placeholder="Cari nama siswa"></div>
                </div>
                <div class="dpe-progress-filter-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index') }}">Reset</a></div>
            </form>
        </div>
    </article>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>{{ $teacher ? 'Laporan siswa Anda' : 'Laporan dalam cakupan Anda' }}</h2><p>Rapor tiga bulan memiliki pengajuan dan persetujuan tersendiri. <a href="{{ route(str_replace('monthly-reports', 'quarterly-reports', $routeBase).'.index') }}">Buka Rapor Tiga Bulan</a></p></div><span class="dpe-dashboard-badge">{{ $reports->total() }} laporan</span></header>
        @if ($rows->isEmpty())
            <x-dashboard.empty title="Belum ada laporan untuk filter ini" :message="$teacher ? 'Buka Perkembangan & Pesan untuk melihat siswa yang ditugaskan. Admin perlu menyiapkan periode dan menetapkan Teacher pemilik laporan. Periksa juga bulan/status yang dipilih.' : 'Belum ada laporan sesuai filter. Pilih Semua status untuk melihat draf dan hasil tinjauan, atau siapkan periode melalui Perkembangan & Pesan.'" icon="report" :action-url="route($progressBase.'.index', array_filter(['month' => $filters['month']]))" action-label="Buka Perkembangan & Pesan" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-monthly-list-table">
                <thead><tr><th>Siswa / Bulan</th><th>Cabang / Teacher</th><th>Kelengkapan</th><th>Status</th><th>Tenggat</th><th>Aksi</th></tr></thead>
                <tbody>@foreach ($rows as $row)
                    @php($report = $row['report'])
                    <tr>
                        <td><strong>{{ $report->student?->full_name ?: 'Siswa tidak tersedia' }}</strong><small>{{ $report->report_month->locale('id')->translatedFormat('F Y') }}</small></td>
                        <td>{{ $report->branch?->name ?: '—' }}<small>{{ $report->teacher?->user?->full_name ?: 'Teacher tidak tersedia' }}</small></td>
                        <td>@if ($row['readiness'])<strong>{{ $row['readiness']['completed'] }} / 8 kategori</strong><small>{{ $row['readiness']['videoCount'] }}{{ $row['readiness']['targetReady'] ? ' / '.$report->video_target.' video' : ' video; target perlu dikonfirmasi' }}</small><span class="dpe-dashboard-badge {{ $row['readiness']['ready'] ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $row['readiness']['ready'] ? 'Isi lengkap' : 'Perlu dilengkapi' }}</span>@else<small>{{ $report->approved_snapshot ? 'Salinan persetujuan tersimpan' : 'Periksa persetujuan lama' }}</small>@endif</td>
                        <td>
                            <x-dashboard.status :status="$report->status" :label="$statusLabels[$report->status] ?? 'Status perlu diperiksa'" />
                            @if ($report->status === 'approved')
                                <x-dashboard.approval :user="$report->approvedBy" :at="$report->approved_at" :timezone="$report->branch?->timezone_name" />
                            @endif
                        </td>
                        <td>{{ $report->due_on?->format('d M Y') ?: '—' }}@if (in_array($report->status, ['draft', 'revision'], true) && $row['overdue'])<small>Lewat tenggat</small>@endif</td>
                        <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($routeBase.'.show', $report) }}"><x-dashboard.icon name="report" />{{ $teacher ? 'Periksa laporan' : 'Tinjau laporan' }}</a></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            <x-dashboard.pagination :paginator="$reports" />
        @endif
    </article>
@endsection
