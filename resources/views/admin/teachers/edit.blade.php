@extends('layouts.dashboard')

@section('title', 'Edit Profil Guru')
@section('heading', 'Profil Guru')
@section('description', 'Perbarui kontak dan akun operasional guru.')

@section('content')
    <x-dashboard.form-panel
        title="Edit profil guru"
        description="Perbarui akun login, nomor WhatsApp, atau foto guru."
        :close-url="route('admin.teachers.show', $teacher)"
    >
        @include('admin.teachers._form')
    </x-dashboard.form-panel>
@endsection
