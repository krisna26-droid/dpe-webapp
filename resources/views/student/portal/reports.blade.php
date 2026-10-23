@extends('layouts.dashboard')
@section('title', 'Rapor Siswa')
@section('heading', 'Rapor Siswa')
@section('description', 'Laporan bulanan dan PDF rapor tiga bulan.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    <div class="dpe-portal-actions dpe-portal-spaced">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('student.dashboard') }}">Kembali ke Dashboard</a>
        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('student.payments.index') }}">Lihat Pembayaran</a>
    </div>
    <article id="laporan-bulanan" class="dpe-dashboard-card dpe-portal-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Laporan bulanan</h2><p>Persetujuan laporan dan pembayaran diperiksa untuk bulan yang sama.</p></div></header>
        @if ($monthlyRows->isEmpty())
            <x-dashboard.empty title="Belum ada laporan bulanan" message="Admin belum menyiapkan periode bulanan untuk siswa ini." icon="report" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-portal-table"><thead><tr><th>Bulan</th><th>Status akses</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($monthlyRows as $row)
                    <tr><td><strong>{{ $row['report']->report_month->locale('id')->translatedFormat('F Y') }}</strong></td><td><span class="dpe-dashboard-badge {{ $row['state']['ready'] ? 'dpe-dashboard-badge--success' : '' }}">{{ $row['state']['ready'] ? 'Tersedia' : 'Terkunci' }}</span></td><td>{{ $row['state']['ready'] ? 'Laporan sudah dapat dibuka.' : implode(' ', $row['state']['reasons']) }}</td><td><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('student.reports.monthly', $row['report']) }}">{{ $row['state']['ready'] ? 'Lihat Laporan' : 'Lihat Syarat' }}</a></td></tr>
                @endforeach
            </tbody></table></div>
            <x-dashboard.pagination :paginator="$monthlyReports" />
        @endif
    </article>
    <article id="rapor-final" class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>PDF rapor tiga bulan</h2><p>Memerlukan tiga laporan dan pembayaran yang disetujui serta persetujuan final.</p></div></header>
        @if ($quarterlyRows->isEmpty())
            <x-dashboard.empty title="Belum ada periode rapor" message="Periode mengikuti tiga bulan kalender sejak siswa bergabung." icon="report" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-portal-table"><thead><tr><th>Periode</th><th>Status akses</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($quarterlyRows as $row)
                    <tr><td><strong>{{ $row['cycle']->start_month->locale('id')->translatedFormat('F Y') }}</strong><small>sampai {{ $row['cycle']->end_month->locale('id')->translatedFormat('F Y') }}</small></td><td><span class="dpe-dashboard-badge {{ $row['state']['ready'] ? 'dpe-dashboard-badge--success' : '' }}">{{ $row['state']['ready'] ? 'Tersedia' : 'Terkunci' }}</span></td><td>{{ $row['state']['ready'] ? 'PDF final tersedia.' : implode(' ', $row['state']['reasons']) }}</td><td>
                        @if ($row['report'])
                            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('student.reports.quarterly', $row['report']) }}">{{ $row['state']['ready'] ? 'Buka Rapor' : 'Lihat Syarat' }}</a>
                        @else
                            <span>Menunggu Teacher</span>
                        @endif
                    </td></tr>
                @endforeach
            </tbody></table></div>
            <x-dashboard.pagination :paginator="$cycles" />
        @endif
    </article>
@endsection

