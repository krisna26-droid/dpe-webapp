@extends('layouts.dashboard')

@section('title', 'Tambah Ketersediaan Guru')
@section('heading', 'Ketersediaan Guru')
@section('description', 'Atur waktu dan jenis kelas pada slot mengajar.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah ketersediaan guru"
        description="Atur waktu dan jenis kelas pada slot mengajar."
        :close-url="route('superadmin.teacher-availability.index')"
    >
        @include('superadmin.teacher-availability._form')
    </x-dashboard.form-panel>
@endsection
