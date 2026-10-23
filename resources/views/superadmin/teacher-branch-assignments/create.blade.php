@extends('layouts.dashboard')

@section('title', 'Tambah Penugasan Guru')
@section('heading', 'Guru & Penugasan')
@section('description', 'Hubungkan guru dengan cabang dan periode tugasnya.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah penugasan guru"
        description="Hubungkan guru dengan cabang dan periode tugasnya."
        :close-url="route('superadmin.teacher-branch-assignments.index')"
    >
        @include('superadmin.teacher-branch-assignments._form')
    </x-dashboard.form-panel>
@endsection
