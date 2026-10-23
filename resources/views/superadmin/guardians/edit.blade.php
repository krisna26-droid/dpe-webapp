@extends('layouts.dashboard')

@section('title', 'Edit Wali Siswa')
@section('heading', 'Wali Siswa')
@section('description', 'Perbarui kontak wali dan penerima utama rapor.')

@section('content')
    <x-dashboard.form-panel
        title="Edit wali siswa"
        description="Perbarui kontak wali dan penerima utama rapor."
        :close-url="route('superadmin.guardians.show', $guardian)"
    >
        @include('superadmin.guardians._form')
    </x-dashboard.form-panel>
@endsection
