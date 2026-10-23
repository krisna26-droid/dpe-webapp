@php
    $stale = $errors->has('expected_snapshot');
    $readonly = $blockReason !== null || $stale;
    $heading = $confirmDelete ? 'Hapus Tautan Video' : ($learningVideo ? 'Edit Video Belajar' : 'Tambah Video Belajar');
@endphp
@extends('layouts.dashboard')
@section('title', $heading)
@section('heading', $heading)
@section('description', 'Tautan video untuk satu siswa dan satu periode pembelajaran.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/student-progress.css') }}?v={{ filemtime(public_path('assets/dpe/student-progress.css')) }}">
@endpush
@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Perkembangan & Pesan / Video Belajar</p><h2>{{ $monthlyReport->student->full_name }}</h2><p>{{ $monthlyReport->report_month->locale('id')->translatedFormat('F Y') }} · {{ $monthlyReport->branch?->name }}</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.edit', $monthlyReport) }}#learning-videos"><x-dashboard.icon name="back" />Kembali ke perkembangan siswa</a></section>
    @if ($blockReason)<div class="dpe-progress-notice" role="status"><x-dashboard.icon name="info" /><p>{{ $blockReason }}</p></div>@endif
    @if ($stale)<div class="dpe-progress-notice" role="alert"><x-dashboard.icon name="warning" /><div><p>{{ $errors->first('expected_snapshot') }}</p><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.edit', $monthlyReport) }}#learning-videos">Muat data terbaru</a></div></div>@endif
    <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>{{ $heading }}</h2><p>{{ $confirmDelete ? 'Pastikan tautan yang akan dihapus sudah benar.' : 'Gunakan tautan lengkap dari tempat video disimpan.' }}</p></div><span class="dpe-dashboard-badge"><x-dashboard.icon name="video" />Video siswa</span></header><div class="dpe-dashboard-card__body">
        @if ($confirmDelete)
            <div class="dpe-progress-delete-preview"><strong>{{ $learningVideo->topic }}</strong><p>{{ $learningVideo->video_on->format('d M Y') }}</p><p>{{ $learningVideo->video_url }}</p><p>{{ $learningVideo->description }}</p></div>
            <p class="dpe-progress-help">Tautan ini akan dihapus dari daftar video siswa pada periode ini. Salinan sebelumnya tetap tercatat dalam riwayat aktivitas petugas.</p>
            @if (! $readonly)<form method="POST" action="{{ route($routeBase.'.videos.delete', [$monthlyReport, $learningVideo]) }}" class="dpe-dashboard-form">@csrf @method('DELETE')<input type="hidden" name="expected_snapshot" value="{{ $expectedSnapshot }}"><div class="dpe-dashboard-form-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.edit', $monthlyReport) }}#learning-videos">Batal</a><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Ya, hapus tautan</button></div></form>@endif
        @else
            <form method="POST" action="{{ $learningVideo ? route($routeBase.'.videos.update', [$monthlyReport, $learningVideo]) : route($routeBase.'.videos.store', $monthlyReport) }}" class="dpe-dashboard-form">@csrf @if ($learningVideo)@method('PUT')@endif<input type="hidden" name="expected_snapshot" value="{{ $expectedSnapshot }}">
                <div class="dpe-dashboard-form-grid">
                    <div class="dpe-dashboard-field"><label for="video-date">Tanggal video</label><input id="video-date" type="date" name="video_on" min="{{ $minDate }}" max="{{ $maxDate }}" value="{{ old('video_on', $learningVideo?->video_on?->format('Y-m-d') ?? $maxDate) }}" required @readonly($readonly)>@error('video_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                    <div class="dpe-dashboard-field"><label for="video-topic">Topik video</label><input id="video-topic" name="topic" maxlength="255" value="{{ old('topic', $learningVideo?->topic) }}" required @readonly($readonly)>@error('topic')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                    <div class="dpe-dashboard-field dpe-progress-full"><label for="video-url">Tautan video</label><input id="video-url" name="video_url" type="url" maxlength="2048" value="{{ old('video_url', $learningVideo?->video_url) }}" placeholder="https://..." required @readonly($readonly)><p class="dpe-progress-help">Pastikan izin tautan memungkinkan penerima yang dituju membuka videonya.</p>@error('video_url')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                    <div class="dpe-dashboard-field dpe-progress-full"><label for="video-session">Pertemuan terkait (opsional)</label><select id="video-session" name="session_id" @disabled($readonly)><option value="">Tanpa hubungan ke pertemuan</option>@foreach ($sessions as $session)<option value="{{ $session->id }}" @selected(old('session_id', $learningVideo?->session_id) === $session->id)>{{ app(\App\Services\StudentAttendanceService::class)->meetingAt($session)->format('d M Y H:i') }} · {{ $session->classGroup?->name ?: 'Private' }}</option>@endforeach</select><p class="dpe-progress-help">Pilih pertemuan dengan peserta siswa ini dan tanggal yang sama dengan video.</p>@error('session_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                    <div class="dpe-dashboard-field dpe-progress-full"><label for="video-description">Keterangan video</label><textarea id="video-description" name="description" rows="4" maxlength="4000" @readonly($readonly)>{{ old('description', $learningVideo?->description) }}</textarea>@error('description')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                </div>
                @if (! $readonly)<div class="dpe-dashboard-form-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.edit', $monthlyReport) }}#learning-videos">Batal</a><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Simpan tautan video</button></div>@endif
            </form>
        @endif
    </div></article>
@endsection
