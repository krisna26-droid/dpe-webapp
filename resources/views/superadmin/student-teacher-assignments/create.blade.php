@extends('layouts.dashboard')
@section('title', 'Tambah Penugasan Siswa')
@section('heading', 'Siswa & Pendaftaran')
@section('description', 'Tugaskan Teacher kepada siswa dengan periode yang jelas.')
@section('content')
    <x-dashboard.form-panel title="Tambah penugasan Teacher" description="Pendaftaran cabang/program dan peserta kelas atau sesi tetap mengikuti data masing-masing." :close-url="route('superadmin.student-teacher-assignments.index')">
        @include('superadmin.student-teacher-assignments._form')
    </x-dashboard.form-panel>
@endsection
