
@extends('layouts.dashboard')
@section('title', 'Rapor Tiga Bulan')
@section('heading', 'Rapor Tiga Bulan')
@section('description', 'Susun empat skill, tinjau rapor final, dan kelola PDF siswa.')
@push('head')
@include('quarterly-reports._head')
@endpush
@section('content')
<section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Laporan / Tiga Bulan</p><h2>{{ $teacher ? 'Rapor akhir untuk siswa Anda.' : 'Tinjau rapor akhir siswa.' }}</h2><p>Periode mengikuti tiga bulan kalender sejak siswa bergabung. Teacher mengisi nilai A/B/C dan deskripsi secara manual.</p></div><div class="dpe-final-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($monthlyBase.'.index') }}">Laporan Bulanan</a>
@if(auth()->user()->role_code === 'superadmin')<a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.report-template.edit') }}">Template PDF</a>
@endif</div></section>
<article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Filter periode</h2><p>Periode tampil setelah laporan bulanan disiapkan untuk siswa.</p></div></header><div class="dpe-dashboard-card__body"><form method="GET" action="{{ route($routeBase.'.index') }}" class="dpe-dashboard-form"><div class="dpe-final-filter-grid"><div class="dpe-dashboard-field"><label for="final-month">Bulan awal (opsional)</label><input id="final-month" name="start_month" type="month" value="{{ $filters['start_month'] }}"></div><div class="dpe-dashboard-field"><label for="final-status">Status</label><select id="final-status" name="status"><option value="all" 
@selected($filters['status'] === 'all')>Semua status</option><option value="unprepared" 
@selected($filters['status'] === 'unprepared')>Belum disiapkan</option>
@foreach($statusLabels as $code => $label)<option value="{{ $code }}" 
@selected($filters['status'] === $code)>{{ $label }}</option>
@endforeach</select></div><div class="dpe-dashboard-field"><label for="final-search">Nama siswa</label><input id="final-search" name="q" type="search" maxlength="100" value="{{ $filters['q'] }}" placeholder="Cari nama siswa"></div><div class="dpe-final-filter-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index') }}">Reset</a></div></div></form></div></article>
<article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Periode rapor siswa</h2><p>Pengajuan dan persetujuan rapor final terpisah dari laporan bulanan.</p></div><span class="dpe-dashboard-badge">{{ $cycles->total() }} periode</span></header>
@if($rows->isEmpty())<x-dashboard.empty title="Belum ada periode sesuai filter" message="Admin perlu menyiapkan periode bulanan dan menetapkan Teacher pemilik melalui Perkembangan & Pesan. Kosongkan bulan awal dan pilih Semua status untuk melihat seluruh periode." icon="report" :action-url="route($progressBase.'.index')" action-label="Buka Perkembangan & Pesan" />
@else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-final-list-table"><thead><tr><th>Siswa / Periode</th><th>Teacher / Cabang</th><th>Status</th><th>Keterangan</th><th>Aksi</th></tr></thead><tbody>
@foreach($rows as $row)
@php($cycle = $row['cycle'])
@php($report = $row['report'])<tr><td><strong>{{ $cycle->student->full_name }}</strong><small>{{ $cycle->start_month->locale('id')->translatedFormat('F Y') }} – {{ $cycle->end_month->locale('id')->translatedFormat('F Y') }}</small></td><td>{{ $report?->teacher?->user?->full_name ?: 'Belum dipilih' }}<small>{{ $report?->branch?->name ?: 'Mengikuti periode bulanan terakhir' }}</small></td><td>
<x-dashboard.status :status="$report?->status ?? 'unprepared'" :label="$report ? ($statusLabels[$report->status] ?? 'Periksa status') : 'Belum disiapkan'" />
@if($report?->status === 'approved')<x-dashboard.approval :user="$report->approvedBy" :at="$report->approved_at" :timezone="$report->branch?->timezone_name" />@endif
</td><td>
@if($report)<small>Revisi {{ $report->revision_number }}
@if(!$row['canOpen']) · Dikelola Teacher pemilik
@endif</small>
@elseif($row['preparation']['errors'])<small>{{ implode(' ', $row['preparation']['errors']) }}</small>
@else<small>Ketiga periode bulanan tersedia.</small>
@endif</td><td>
@if($row['canOpen'])<a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($routeBase.'.show', $report) }}">Periksa rapor</a>
@elseif($row['canPrepare'])<a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($routeBase.'.prepare', $cycle) }}">Siapkan rapor</a>
@elseif(!$report)<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($progressBase.'.index', ['month' => $cycle->start_month->format('Y-m')]) }}">Periksa periode bulanan</a>
@else<span>Teacher pemilik</span>
@endif</td></tr>
@endforeach
</tbody></table></div><x-dashboard.pagination :paginator="$cycles" />
@endif</article>
@endsection
