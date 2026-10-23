@extends('layouts.dashboard')
@section('title', 'Status Akses Rapor')
@section('heading', 'Status Akses Rapor')
@section('description', 'Periksa syarat yang belum terpenuhi.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    @include('student.portal._nav', ['back' => route('student.reports.index'), 'backLabel' => 'Kembali ke Rapor Siswa'])
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>{{ $period }}</h2><p>Rapor masih terkunci.</p></div><span class="dpe-dashboard-badge">Terkunci</span></header>
        <div class="dpe-dashboard-card__body">
            <ul class="dpe-portal-reasons">@foreach ($reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul>
            @if (isset($months))
                <div class="dpe-portal-grid dpe-portal-spaced">
                    @foreach ($months as $month)
                        <div class="dpe-portal-item"><strong>{{ $month['label'] }}</strong><p>Laporan: {{ $month['monthly_ok'] ? 'Disetujui' : 'Belum disetujui' }}</p><p>Pembayaran: {{ $month['paid'] ? 'Tervalidasi' : 'Belum tervalidasi' }}</p></div>
                    @endforeach
                </div>
            @endif
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('student.payments.index') }}">Lihat Pembayaran</a>
        </div>
    </article>
@endsection

