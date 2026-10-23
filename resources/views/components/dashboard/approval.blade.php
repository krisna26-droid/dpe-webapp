@props(['user' => null, 'at' => null, 'timezone' => null])

@php
    $approverName = trim((string) $user?->full_name);
    if ($approverName === '') $approverName = trim((string) $user?->username);
    $approverRole = ['admin' => 'Admin Cabang', 'superadmin' => 'Super Admin'][$user?->role_code] ?? null;
    $approvalZone = is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
        ? $timezone : config('app.timezone', 'Asia/Makassar');
    $approvalTime = $at?->copy()->timezone($approvalZone)->locale('id');
@endphp

@once
    @push('head')
        <link rel="stylesheet" href="{{ asset('assets/dpe/approval.css') }}?v={{ filemtime(public_path('assets/dpe/approval.css')) }}">
    @endpush
@endonce

<div {{ $attributes->class('dpe-approval-meta') }}>
    <span>Disetujui oleh {{ $approverName !== '' ? $approverName : 'Nama belum tersedia' }}@if ($approverRole) <span>({{ $approverRole }})</span>@endif</span>
    @if ($approvalTime)
        <time datetime="{{ $approvalTime->toIso8601String() }}">{{ $approvalTime->translatedFormat('d F Y, H:i') }} {{ $approvalTime->format('T') }}</time>
    @else
        <span>Waktu persetujuan belum tersedia</span>
    @endif
</div>
