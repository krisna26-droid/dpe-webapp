
@extends('layouts.dashboard')
@section('title', 'Siapkan Rapor Tiga Bulan')
@section('heading', 'Siapkan Rapor Tiga Bulan')
@section('description', 'Tetapkan Teacher pemilik sebelum mengisi rapor final.')
@push('head')
@include('quarterly-reports._head')
@endpush
@section('content')
<section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">{{ $cycle->student->full_name }}</p><h2>{{ $cycle->start_month->locale('id')->translatedFormat('F Y') }} – {{ $cycle->end_month->locale('id')->translatedFormat('F Y') }}</h2><p>Pilih Teacher dari pemilik laporan bulanan dalam periode ini. Cabang mengikuti periode bulanan terakhir.</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index') }}"><x-dashboard.icon name="back" />Kembali ke Rapor Tiga Bulan</a></section>
<article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Periode bulanan</h2><p>Ketiganya harus disiapkan. Isi bulanan dapat masih draf saat rapor final mulai disusun.</p></div></header><div class="dpe-dashboard-card__body"><div class="dpe-final-source-grid">
@foreach($context['sources'] as $source)<div class="dpe-final-source"><strong>{{ \Carbon\CarbonImmutable::parse($source['month'])->locale('id')->translatedFormat('F Y') }}</strong><p>{{ $source['matches_cycle'] ? ($statusLabels[$source['report']->status] ?? 'Periksa status') : 'Periode belum sesuai / belum disiapkan' }}</p></div>
@endforeach</div>
@if($context['errors'])<div class="dpe-final-notice">
@foreach($context['errors'] as $error)<p>{{ $error }}</p>
@endforeach<a href="{{ route($progressBase.'.index', ['month' => $cycle->start_month->format('Y-m')]) }}">Buka Perkembangan &amp; Pesan</a></div>
@endif</div></article>
<article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Teacher pemilik rapor final</h2><p>Teacher terpilih mengisi empat skill dan mengajukan rapor untuk tinjauan.</p></div></header><div class="dpe-dashboard-card__body"><form method="POST" action="{{ route($routeBase.'.store', $cycle) }}" class="dpe-dashboard-form">
@csrf<input type="hidden" name="expected_snapshot" value="{{ old('expected_snapshot', $expectedSnapshot) }}">
@if($teacher)<input type="hidden" name="teacher_id" value="{{ $ownTeacherId }}"><div class="dpe-final-notice">Teacher pemilik: <strong>{{ auth()->user()->full_name }}</strong></div>
@else<div class="dpe-dashboard-field"><label for="final-owner">Teacher</label><select id="final-owner" name="teacher_id" required><option value="">Pilih Teacher pemilik</option>
@foreach($owners as $owner)<option value="{{ $owner->id }}" 
@selected(old('teacher_id', $owners->count() === 1 ? $owners->first()->id : null) === $owner->id)>{{ $owner->user->full_name }}</option>
@endforeach</select></div>
@endif
@if($owners->isEmpty())<p class="dpe-final-notice">Belum ada Teacher pemilik bulanan dengan profil dan akun aktif. Periksa pemilik periode bulanan terlebih dahulu.</p>
@endif
<div class="dpe-final-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" 
@disabled($context['errors'] || $owners->isEmpty() || ($teacher && !$owners->contains('id', $ownTeacherId)) || (old('expected_snapshot') && old('expected_snapshot') !== $expectedSnapshot))>Siapkan draf rapor</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index') }}">Batal</a></div>
@if(old('expected_snapshot') && old('expected_snapshot') !== $expectedSnapshot)<p class="dpe-final-notice">Data periode berubah. <a href="{{ route($routeBase.'.prepare', $cycle) }}">Muat formulir terbaru</a> sebelum memilih Teacher kembali.</p>
@endif
</form></div></article>
@endsection
