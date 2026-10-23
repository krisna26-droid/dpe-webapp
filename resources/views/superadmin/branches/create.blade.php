@extends('layouts.dashboard')

@section('title', 'Tambah Cabang')
@section('heading', 'Cabang')
@section('description', 'Siapkan lokasi baru dan atur rekap pembayarannya.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah cabang"
        description="Siapkan lokasi baru dan atur rekap pembayarannya."
        :close-url="route('superadmin.branches.index')"
    >
        @include('superadmin.branches._form')
    </x-dashboard.form-panel>
@endsection
