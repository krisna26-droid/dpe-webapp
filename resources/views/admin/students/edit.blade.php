@extends('layouts.dashboard')

@section('title', 'Edit Profil Siswa')
@section('heading', 'Data Siswa')
@section('description', 'Perbarui identitas dan catatan operasional siswa.')

@section('content')
    <x-dashboard.form-panel
        title="Edit profil siswa"
        description="Perbarui data identitas siswa yang terhubung ke cabang Anda."
        :close-url="route('admin.students.show', $student)"
    >
        @include('admin.students._form')
    </x-dashboard.form-panel>
@endsection
