@extends('layouts.dashboard')

@section('title', 'Tambah Pendaftaran Siswa')
@section('heading', 'Pendaftaran Siswa')
@section('description', 'Pilih program dan cabang untuk satu periode belajar.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah pendaftaran siswa"
        description="Pilih program dan cabang untuk satu periode belajar."
        :close-url="route('superadmin.student-enrollments.index')"
    >
        @include('superadmin.student-enrollments._form')
    </x-dashboard.form-panel>
@endsection
