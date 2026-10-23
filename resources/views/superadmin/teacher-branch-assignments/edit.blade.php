@extends('layouts.dashboard')

@section('title', 'Edit Penugasan Guru')
@section('heading', 'Guru & Penugasan')
@section('description', 'Perbarui cabang dan periode penugasan guru.')

@section('content')
    <x-dashboard.form-panel
        title="Edit penugasan guru"
        description="Perbarui cabang dan periode penugasan guru."
        :close-url="route('superadmin.teacher-branch-assignments.show', $assignment)"
    >
        @include('superadmin.teacher-branch-assignments._form')
    </x-dashboard.form-panel>
@endsection
