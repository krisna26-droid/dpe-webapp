@extends('layouts.dashboard')

@section('title', 'Edit Tantangan Siswa')
@section('heading', 'Tantangan Siswa')
@section('description', 'Perbarui catatan tantangan belajar siswa dan referensi sesi terkait.')

@section('content')
    <x-dashboard.form-panel
        title="Edit tantangan siswa"
        description="Perbarui data pencatatan, kategori, dan catatan pendampingan siswa."
        :close-url="route('superadmin.student-challenges.show', $studentChallenge)"
    >
        @include('student-challenges._form')
    </x-dashboard.form-panel>
@endsection
