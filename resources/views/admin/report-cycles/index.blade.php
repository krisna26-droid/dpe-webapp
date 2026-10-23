@extends('layouts.dashboard')
@section('title', 'Siklus Laporan')
@section('heading', 'Siklus Laporan')
@section('description', 'Periksa periode rapor tiga bulan dan laporan bulanan siswa.')
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Laporan / Periode</p><h2>Siklus laporan siswa</h2><p>Periode disiapkan melalui Perkembangan & Pesan, bersama Teacher dan target belajar yang sesuai.</p></div>
        <div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($operationRoutePrefix.'.student-progress.index') }}">Siapkan periode bulanan</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.dashboard') }}"><x-dashboard.icon name="back" />Kembali</a></div>
    </section>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Daftar siklus</h2><p>{{ $cycles->total() }} periode tercatat</p></div></header>
        <div class="dpe-dashboard-card__body"><form method="GET" action="{{ route('admin.report-cycles.index') }}" class="dpe-operations-filter"><div class="dpe-dashboard-field"><label for="cycle-query">Nama siswa</label><input id="cycle-query" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="120" placeholder="Cari nama siswa"></div><div class="dpe-operations-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.report-cycles.index') }}">Reset</a></div></form></div>
        @if($cycles->isEmpty())
            <x-dashboard.empty title="Belum ada siklus laporan" message="Buka Perkembangan & Pesan untuk memilih siswa dan menyiapkan periode bulanannya." :action-url="route($operationRoutePrefix.'.student-progress.index')" action-label="Siapkan periode" icon="report" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table"><thead><tr><th>Siswa</th><th>Siklus</th><th>Periode</th><th>Laporan bulanan</th><th>Tenggat rapor final</th><th>Aksi</th></tr></thead><tbody>
                @foreach($cycles as $cycle)
                    <tr><td><strong>{{ $cycle->student?->full_name ?: 'Siswa' }}</strong></td><td>{{ $cycle->cycle_number }}</td><td><strong>{{ $cycle->start_month->translatedFormat('F Y') }}</strong><br><span>s.d. {{ $cycle->end_month->translatedFormat('F Y') }}</span></td><td class="dpe-report-cycle-monthly-cell">
                        @if($cycle->monthlyReports->isEmpty())
                            <span>Periode bulanan belum disiapkan pada cabang Anda.</span>
                        @else
                            <ul class="dpe-report-cycle-monthly-list">
                                @foreach($cycle->monthlyReports as $report)
                                    <li class="dpe-report-cycle-monthly-item">
                                        <a href="{{ route($operationRoutePrefix.'.monthly-reports.show', $report) }}">{{ $report->report_month->translatedFormat('F Y') }}</a>
                                        <x-dashboard.status :status="$report->status" :label="\App\Services\MonthlyReportContentService::STATUSES[$report->status] ?? $report->status" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </td><td>{{ $cycle->share_due_on?->format('d M Y') ?: 'Belum ditentukan' }}</td><td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.student-progress.preparation', ['student' => $cycle->student_id, 'month' => $cycle->start_month->format('Y-m')]) }}">Periksa periode bulanan</a></td></tr>
                @endforeach
            </tbody></table></div>
            <x-dashboard.pagination :paginator="$cycles" />
        @endif
    </article>
@endsection
