@extends('layouts.dashboard')

@section('title', 'Tambah Profil Siswa')
@section('heading', 'Profil Siswa')
@section('description', 'Lengkapi identitas anak dan hubungkan akun portalnya.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah profil siswa"
        description="Lengkapi identitas anak dan hubungkan akun portalnya."
        :close-url="route('superadmin.students.index')"
    >
        @include('superadmin.students._form')
    </x-dashboard.form-panel>
@endsection
