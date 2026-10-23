@extends('layouts.dashboard')

@section('title', 'Edit Pesan Guru')
@section('heading', 'Pesan Guru')
@section('description', 'Perbarui isi pesan dan relasi pembelajaran yang terkait.')

@section('content')
    <x-dashboard.form-panel
        title="Edit pesan guru"
        description="Perubahan akan memperbarui riwayat komunikasi yang tersimpan untuk siswa."
        :close-url="route('superadmin.teacher-messages.show', $teacherMessage)"
    >
        @include('teacher-messages._form')
    </x-dashboard.form-panel>
@endsection
