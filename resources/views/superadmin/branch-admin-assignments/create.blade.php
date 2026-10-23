@extends('layouts.dashboard')

@section('title', 'Tambah Penugasan Admin Cabang')
@section('heading', 'Admin Cabang')
@section('description', 'Hubungkan akun Admin Cabang dengan cabang dan periode tanggung jawabnya.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah penugasan admin cabang"
        description="Pilih akun admin, cabang, dan periode tanggung jawabnya."
        :close-url="route('superadmin.branch-admin-assignments.index')"
    >
        @include('superadmin.branch-admin-assignments._form')
    </x-dashboard.form-panel>
@endsection
