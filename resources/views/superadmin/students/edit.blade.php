@extends('layouts.dashboard')

@section('title', 'Edit Profil Siswa')
@section('heading', 'Profil Siswa')
@section('description', 'Perbarui identitas dan akun portal siswa.')

@section('content')
    <x-dashboard.form-panel
        title="Edit profil siswa"
        description="Perbarui identitas dan akun portal siswa."
        :close-url="route('superadmin.students.show', $student)"
    >
        @include('superadmin.students._form')
    </x-dashboard.form-panel>
@endsection
