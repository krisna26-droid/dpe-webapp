@extends('layouts.dashboard')

@section('title', 'Edit Penugasan Admin Cabang')
@section('heading', 'Admin Cabang')
@section('description', 'Perbarui cabang dan periode tanggung jawab admin.')

@section('content')
    <x-dashboard.form-panel
        title="Edit penugasan admin cabang"
        description="Perbarui cabang dan periode tanggung jawab admin."
        :close-url="route('superadmin.branch-admin-assignments.show', $assignment)"
    >
        @include('superadmin.branch-admin-assignments._form')
    </x-dashboard.form-panel>
@endsection
