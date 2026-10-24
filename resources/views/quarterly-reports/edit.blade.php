
@extends('layouts.dashboard')
@section('title', $teacher ? 'Isi Rapor Tiga Bulan' : 'Teacher Pemilik Rapor')
@section('heading', $teacher ? 'Isi Rapor Tiga Bulan' : 'Teacher Pemilik Rapor')
@section('description', 'Simpan draf, lalu periksa rapor sebelum pengajuan.')
@push('head')
@include('quarterly-reports._head')
@endpush
@section('content')
@php($stale = old('expected_snapshot') && old('expected_snapshot') !== $expectedSnapshot)
<section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">{{ $report->student->full_name }} / Revisi {{ $report->revision_number }}</p><h2>{{ $report->cycle->start_month->locale('id')->translatedFormat('F Y') }} – {{ $report->cycle->end_month->locale('id')->translatedFormat('F Y') }}</h2><p>{{ $teacher ? 'Isi delapan skill secara manual. Nilai A/B/C tidak dihitung dari tren laporan bulanan.' : 'Admin dapat mengganti Teacher pemilik saat Draf atau Perlu revisi. Isian skill dan Note dikerjakan Teacher.' }}</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.show', $report) }}"><x-dashboard.icon name="back" />Kembali ke rapor</a></section>
@if($stale)<div class="dpe-final-notice dpe-progress-spaced">Rapor atau sumber berubah sejak formulir dibuka. Isian ini belum disimpan. <a href="{{ route($routeBase.'.edit', $report) }}">Muat formulir terbaru</a> dan periksa kembali.</div>
@endif
<form method="POST" action="{{ route($routeBase.'.save', $report) }}" class="dpe-dashboard-form">
@csrf 
@method('PUT')<input type="hidden" name="expected_snapshot" value="{{ old('expected_snapshot', $expectedSnapshot) }}">
@if(!$teacher)<article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><h2>Teacher pemilik</h2></header><div class="dpe-dashboard-card__body"><div class="dpe-dashboard-field"><label for="final-owner">Teacher</label><select id="final-owner" name="teacher_id" required><option value="">Pilih Teacher pemilik</option>
@foreach($owners as $owner)<option value="{{ $owner->id }}" 
@selected(old('teacher_id', $report->teacher_id) === $owner->id)>{{ $owner->user->full_name }}</option>
@endforeach</select><p class="dpe-dashboard-field__hint">Pilihan berasal dari Teacher aktif pemilik laporan bulanan dalam siklus ini.</p></div></div></article>
@endif
@if($teacher)<article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Delapan skill</h2><p>A: 90–100 (Very Good) · B: 80–89 (Good) · C: 70–79 (Fair)</p></div></header><div class="dpe-dashboard-card__body dpe-final-editor-grid">
@foreach($skillLabels as $code => $label)<fieldset class="dpe-final-skill"><legend>{{ $label }}</legend><div class="dpe-dashboard-field"><label for="score-{{ $code }}">Skor</label><select id="score-{{ $code }}" name="skills[{{ $code }}][score]"><option value="">Belum diisi</option>
@foreach(['A','B','C'] as $score)<option value="{{ $score }}" 
@selected(old('skills.'.$code.'.score', $report->skills[$code]['score'] ?? '') === $score)>{{ $score }}</option>
@endforeach</select></div><div class="dpe-dashboard-field"><label for="description-{{ $code }}">Deskripsi {{ $label }}</label><textarea id="description-{{ $code }}" name="skills[{{ $code }}][description]" rows="6" maxlength="4000">{{ old('skills.'.$code.'.description', $report->skills[$code]['description'] ?? '') }}</textarea><p class="dpe-dashboard-field__hint">Maksimal 4.000 karakter. Untuk draf, deskripsi dan skor boleh sama-sama kosong.</p></div></fieldset>
@endforeach</div></article>
<article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Note untuk orang tua</h2><p>Note tampil setelah tabel delapan skill pada rapor PDF.</p></div></header><div class="dpe-dashboard-card__body"><div class="dpe-dashboard-field"><label for="final-note">Note</label><textarea id="final-note" name="parent_note" rows="7" maxlength="8000">{{ old('parent_note', $report->parent_note) }}</textarea><p class="dpe-dashboard-field__hint">Wajib sebelum pengajuan, maksimal 8.000 karakter.</p></div></div></article>
@endif
<div class="dpe-final-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" name="next" value="review" 
@disabled($stale)>Simpan &amp; periksa rapor</button><button class="dpe-dashboard-button dpe-dashboard-button--outline" type="submit" name="next" value="edit" 
@disabled($stale)>Simpan draf</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.show', $report) }}">Batal</a></div></form>
@endsection
