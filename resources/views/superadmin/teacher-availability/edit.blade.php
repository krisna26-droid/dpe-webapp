@extends('layouts.dashboard')

@section('title', 'Edit Ketersediaan Guru')
@section('heading', 'Ketersediaan Guru')
@section('description', 'Perbarui waktu, cabang, dan status slot mengajar.')

@section('content')
    <x-dashboard.form-panel
        title="Edit ketersediaan guru"
        description="Perbarui waktu, cabang, dan status slot mengajar."
        :close-url="route('superadmin.teacher-availability.show', $availability)"
    >
        @include('superadmin.teacher-availability._form')
    </x-dashboard.form-panel>
@endsection
