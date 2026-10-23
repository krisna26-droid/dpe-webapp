@extends('layouts.dashboard')

@section('title', 'Tambah Profil Guru')
@section('heading', 'Profil Guru')
@section('description', 'Hubungkan akun role Guru dengan data operasional guru.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah profil guru"
        description="Pilih akun login guru, lalu lengkapi nomor WhatsApp untuk kebutuhan operasional."
        :close-url="auth()->user()?->role_code === 'superadmin'
            ? route('superadmin.teacher-branch-assignments.index')
            : route('admin.teachers.index')"
    >
        @include('admin.teachers._form')
    </x-dashboard.form-panel>
@endsection
