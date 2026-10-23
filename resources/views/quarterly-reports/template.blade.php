
@extends('layouts.dashboard')
@section('title', 'Template PDF Rapor')
@section('heading', 'Template PDF Rapor')
@section('description', 'Identitas lembaga dan penutup untuk persetujuan rapor berikutnya.')
@push('head')
@include('quarterly-reports._head')
@endpush
@section('content')
@php($stale = old('expected_snapshot') && old('expected_snapshot') !== $expectedSnapshot)
<section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Rapor / Template PDF</p><h2>Kop dan penutup rapor.</h2><p>Logo mengikuti pengaturan lembaga. Rapor yang sudah disetujui menyimpan template dan logo saat persetujuan.</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.quarterly-reports.index') }}"><x-dashboard.icon name="back" />Kembali ke Rapor Tiga Bulan</a></section>
<article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Pengaturan lembaga</h2><p>Revisi template {{ $settings['revision'] }}. Simpan nama Manager sebelum mengunggah tanda tangan pada bagian di bawah.</p></div></header><div class="dpe-dashboard-card__body"><form method="POST" action="{{ route('superadmin.report-template.save') }}" class="dpe-dashboard-form">
@csrf 
@method('PUT')<input type="hidden" name="expected_snapshot" value="{{ old('expected_snapshot', $expectedSnapshot) }}">
@foreach($fields as $code => $label)<div class="dpe-dashboard-field"><label for="template-{{ $code }}">{{ $label }}</label>
@if(in_array($code, ['instagram_accounts','documentation_text','closing_text'], true))<textarea id="template-{{ $code }}" name="{{ $code }}" rows="{{ $code === 'instagram_accounts' ? 4 : 6 }}" maxlength="{{ $code === 'instagram_accounts' ? 1000 : 8000 }}" required>{{ old($code, $settings['fields'][$code]) }}</textarea>
@else<input id="template-{{ $code }}" name="{{ $code }}" maxlength="255" value="{{ old($code, $settings['fields'][$code]) }}" required>
@endif</div>
@endforeach
@if($stale)<p class="dpe-final-notice">Template berubah. <a href="{{ route('superadmin.report-template.edit') }}">Muat pengaturan terbaru</a> sebelum menyimpan.</p>
@endif<div class="dpe-final-actions dpe-report-template-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" 
@disabled($stale)>Simpan template</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.quarterly-reports.index') }}">Batal</a></div></form></div></article>
@include('report-signatures.form', ['signatureScope' => 'manager', 'signatureRouteBase' => 'superadmin.report-template.signature'])
@endsection
