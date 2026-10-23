@extends('layouts.dashboard')

@section('title', 'Edit Video Belajar')
@section('heading', 'Video Belajar')
@section('description', 'Perbarui dokumentasi video pembelajaran siswa.')

@section('content')
    <x-dashboard.form-panel
        title="Edit video belajar"
        description="Perbarui informasi video dan sesi pembelajaran yang terkait."
        :close-url="route('superadmin.learning-videos.show', $learningVideo)"
    >
        @include('learning-videos._form')
    </x-dashboard.form-panel>
@endsection
