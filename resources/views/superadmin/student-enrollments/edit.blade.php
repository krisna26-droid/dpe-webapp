@extends('layouts.dashboard')

@section('title', 'Edit Pendaftaran Siswa')
@section('heading', 'Pendaftaran Siswa')
@section('description', 'Perbarui program, cabang, dan periode belajar.')

@section('content')
    <x-dashboard.form-panel
        title="Edit pendaftaran siswa"
        description="Perbarui program, cabang, dan periode belajar."
        :close-url="route('superadmin.student-enrollments.show', $enrollment)"
    >
        @include('superadmin.student-enrollments._form')
    </x-dashboard.form-panel>
@endsection
