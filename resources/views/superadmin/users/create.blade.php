@extends('layouts.dashboard')

@section('title', 'Tambah Pengguna')
@section('heading', 'Pengguna')
@section('description', 'Buat akun dan atur akses pengguna ke portal DPE.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah pengguna"
        description="Lengkapi identitas akun, role, dan password untuk masuk ke portal."
        :close-url="route('superadmin.users.index')"
    >
        @include('superadmin.users._form')
    </x-dashboard.form-panel>
@endsection
