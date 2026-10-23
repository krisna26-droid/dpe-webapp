@extends('layouts.dashboard')

@section('title', 'Tambah Kompetensi')
@section('heading', 'Kompetensi & Skill')
@section('description', 'Tambahkan kompetensi yang akan menjadi acuan penilaian siswa.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah kompetensi"
        description="Isi kode, nama, dan urutan tampil skill pada laporan perkembangan siswa."
        :close-url="route('superadmin.learning-skills.index')"
    >
        @include('learning-skills._form')
    </x-dashboard.form-panel>
@endsection
