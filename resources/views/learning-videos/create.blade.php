@extends('layouts.dashboard')

@section('title', 'Tambah Video Belajar')
@section('heading', 'Video Belajar')
@section('description', 'Catat video pembelajaran siswa dan hubungkan dengan sesi jika tersedia.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah video belajar"
        description="Pilih siswa, guru, tanggal, topik, dan tautan video pembelajaran."
        :close-url="route('superadmin.learning-videos.index')"
    >
        @include('learning-videos._form')
    </x-dashboard.form-panel>
@endsection
