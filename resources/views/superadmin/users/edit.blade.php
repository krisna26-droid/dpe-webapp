@php($managedUser = $user)
@extends('layouts.dashboard')
@section('title', 'Edit Pengguna')
@section('heading', 'Pengguna')
@section('description', 'Perbarui identitas akun dan pengaturan akses portal.')
@section('content')
    <x-dashboard.form-panel title="Edit pengguna" description="Kosongkan password untuk mempertahankan password yang sudah digunakan." :close-url="route('superadmin.users.show', $managedUser)">
        @include('superadmin.users._form', ['managedUser' => $managedUser])
    </x-dashboard.form-panel>
@endsection
