@extends('layouts.dashboard')
@section('title', 'Edit Penugasan Siswa')
@section('heading', 'Siswa & Pendaftaran')
@section('description', 'Perbarui Teacher dan tanggal penugasan langsung siswa.')
@section('content')
    <x-dashboard.form-panel title="Edit penugasan Teacher" description="Perubahan penugasan tidak memindahkan pemilik laporan bulanan yang sudah dibuat." :close-url="route('superadmin.student-teacher-assignments.show', $assignment)">
        @include('superadmin.student-teacher-assignments._form')
    </x-dashboard.form-panel>
@endsection
