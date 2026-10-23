@extends('layouts.dashboard')
@section('title', 'Hasil Pemeriksaan Kehadiran')
@section('heading', 'Hasil Pemeriksaan Kehadiran')
@section('description', 'Lihat catatan kehadiran Teacher yang dirujuk oleh notifikasi.')
@section('content')
    <div class="dpe-operations-actions dpe-operations-spaced"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('inbox.index') }}">Kembali ke Notifikasi</a><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('teacher.attendance.index') }}#riwayat-absensi">Kehadiran Teacher</a></div>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>{{ $attendance->branch?->name ?: 'Kehadiran Teacher' }}</h2><p>{{ $attendance->recordDate()?->format('d/m/Y') }} · {{ $attendance->modeLabel() }}</p></div><span class="dpe-dashboard-badge">{{ ['pending_review' => 'Menunggu pemeriksaan', 'needs_correction' => 'Perlu koreksi', 'approved' => 'Disetujui'][$attendance->status] ?? 'Perlu diperiksa' }}</span></header>
        <div class="dpe-dashboard-card__body dpe-operations-stack">
            <div><h3>Waktu pelaksanaan</h3><p>Datang/mulai: {{ $attendance->check_in_at?->format('d/m/Y H:i') ?: 'Belum dicatat' }}</p><p>Pulang/selesai: {{ $attendance->check_out_at?->format('d/m/Y H:i') ?: 'Belum dicatat' }}</p></div>
            <div><h3>Catatan pemeriksaan</h3><p class="dpe-inbox-copy">{{ $attendance->review_note ?: 'Belum ada catatan pemeriksaan.' }}</p>@if ($attendance->status === 'needs_correction')<p>Hubungi Admin Cabang untuk pemeriksaan dan koreksi catatan ini.</p>@endif</div>
        </div>
    </article>
@endsection
