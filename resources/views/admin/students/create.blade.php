@extends('layouts.dashboard')

@section('title', 'Tambah Profil Siswa')
@section('heading', 'Data Siswa')
@section('description', 'Hubungkan akun portal siswa dengan profil operasionalnya.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah profil siswa"
        description="Pilih akun portal siswa, lalu lengkapi identitas dan tanggal mulai belajar."
        :close-url="route('admin.students.index')"
    >
        @include('admin.students._form')
    </x-dashboard.form-panel>
@endsection
