@php
    $managedUser = $user;
    $roleLabels = ['superadmin' => 'Super Admin', 'admin' => 'Admin Cabang', 'teacher' => 'Teacher', 'student' => 'Siswa / Parent'];
@endphp
@extends('layouts.dashboard')
@section('title', 'Detail Pengguna')
@section('heading', 'Pengguna')
@section('description', 'Periksa identitas, role, dan status akun portal.')
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Operasional / Akses</p><h2>{{ $managedUser->full_name }}</h2><p>{{ $roleLabels[$managedUser->role_code] ?? $managedUser->role_code }}</p></div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.users.edit', $managedUser) }}"><x-dashboard.icon name="settings" /> Edit pengguna</a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.users.index') }}"><x-dashboard.icon name="back" /> Kembali ke Pengguna</a>
        </div>
    </section>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Identitas akun</h2><p>Akun yang terhubung dengan profil siswa atau Teacher harus mempertahankan role profilnya.</p></div><span class="dpe-dashboard-badge {{ $managedUser->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">{{ $managedUser->is_active ? 'Aktif' : 'Nonaktif' }}</span></header>
        <div class="dpe-dashboard-card__body">
            <div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Nama lengkap</p><p class="dpe-dashboard-detail__value">{{ $managedUser->full_name }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Username</p><p class="dpe-dashboard-detail__value">{{ $managedUser->username }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Email</p><p class="dpe-dashboard-detail__value">{{ $managedUser->email ?: 'Belum diisi' }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Dibuat</p><p class="dpe-dashboard-detail__value">{{ $managedUser->created_at?->format('d M Y') ?: '—' }}</p></div>
            </div>
            @if ($managedUser->id !== auth()->id())
                <form method="POST" action="{{ route('superadmin.users.status', $managedUser) }}" class="dpe-dashboard-form-actions" data-confirm-form data-confirm-title="Ubah status akun ini?" data-confirm-message="Status menentukan apakah akun ini dapat masuk ke portal." data-confirm-action="Ubah status">
                    @csrf
                    @method('PATCH')
                    <button class="dpe-dashboard-button {{ $managedUser->is_active ? 'dpe-dashboard-button--danger' : 'dpe-dashboard-button--primary' }}" type="submit">{{ $managedUser->is_active ? 'Nonaktifkan akun' : 'Aktifkan akun' }}</button>
                </form>
            @endif
        </div>
    </article>
@endsection
