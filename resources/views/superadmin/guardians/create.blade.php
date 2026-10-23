@extends('layouts.dashboard')

@section('title', 'Tambah Wali Siswa')
@section('heading', 'Wali Siswa')
@section('description', 'Simpan kontak orang tua atau wali sebagai penerima rapor.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah wali siswa"
        description="Simpan kontak orang tua atau wali sebagai penerima rapor."
        :close-url="route('superadmin.guardians.index')"
    >
        @include('superadmin.guardians._form')
    </x-dashboard.form-panel>
@endsection
