@extends('layouts.dashboard')

@section('title', 'Tambah Tantangan Siswa')
@section('heading', 'Tantangan Siswa')
@section('description', 'Catat area belajar yang perlu dilatih dan dipantau bersama guru.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah tantangan siswa"
        description="Pilih siswa, guru, tanggal pencatatan, dan detail tantangan belajar."
        :close-url="route('superadmin.student-challenges.index')"
    >
        @include('student-challenges._form')
    </x-dashboard.form-panel>
@endsection
