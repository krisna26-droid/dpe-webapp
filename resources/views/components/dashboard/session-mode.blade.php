@props(['prefix', 'weekly' => false])
@php
    $listParameters = app(\App\Services\LessonSessionListService::class)->parameters(request());
@endphp
<nav class="dpe-dashboard-page-header__actions" aria-label="Jenis jadwal">
    <a class="dpe-dashboard-button {{ $weekly ? 'dpe-dashboard-button--outline' : 'dpe-dashboard-button--primary' }}" href="{{ route($prefix.'.lesson-sessions.create', $listParameters) }}" @if(!$weekly) aria-current="page" @endif>Sekali pertemuan</a>
    <a class="dpe-dashboard-button {{ $weekly ? 'dpe-dashboard-button--primary' : 'dpe-dashboard-button--outline' }}" href="{{ route($prefix.'.lesson-series.create', $listParameters) }}" @if($weekly) aria-current="page" @endif>Berulang mingguan</a>
</nav>
