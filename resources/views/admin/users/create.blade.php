@extends('layouts.dashboard')
@section('title','Tambah Pengguna Siswa')
@section('heading','Pengguna')
@section('description','Buat akun siswa beserta profil, wali, guru dan sesi belajar.')
@push('head')<link rel="stylesheet" href="{{ asset('assets/dpe/own-account.css') }}?v={{ filemtime(public_path('assets/dpe/own-account.css')) }}">@endpush
@push('scripts')<script src="{{ asset('assets/dpe/own-account.js') }}" defer></script><script src="{{ asset('assets/dpe/student-account.js') }}?v={{ filemtime(public_path('assets/dpe/student-account.js')) }}" defer></script>@endpush
@section('content')
<x-dashboard.form-panel title="Tambah pengguna siswa" description="Satu kali simpan untuk melengkapi pendaftaran siswa." :close-url="route('admin.users.index')">
@if($errors->any())<div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
@if($branches->isEmpty())<div class="dpe-dashboard-alert dpe-dashboard-alert--warning" role="alert">Belum ada penugasan cabang aktif. Minta Super Admin menyiapkan penugasan cabang terlebih dahulu.</div>@elseif($accountSessions->isEmpty())<div class="dpe-dashboard-alert dpe-dashboard-alert--warning" role="alert">Belum ada sesi belajar aktif. Buat jadwal kelompok terlebih dahulu.</div>@endif
<form method="POST" action="{{ route('admin.users.store') }}">@csrf
    <x-dashboard.student-account-fields :branches="$branches" :account-teachers="$accountTeachers" :account-sessions="$accountSessions" :account-session-choices="$accountSessionChoices" :account-teacher-branches="$accountTeacherBranches" />
    <div class="dpe-dashboard-form-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.users.index') }}">Batal</a><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($branches->isEmpty() || $accountSessions->isEmpty())>Simpan siswa</button></div>
</form></x-dashboard.form-panel>
@endsection
