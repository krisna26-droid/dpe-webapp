@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@extends('layouts.dashboard')

@section('title', 'Edit Anggota Kelompok')
@section('heading', 'Kelompok Kelas')
@section('description', 'Perbarui kelompok dan periode belajar siswa.')

@section('content')
    <x-dashboard.form-panel
        title="Edit anggota kelompok"
        description="Riwayat lama tetap tersimpan sebagai bagian dari perjalanan belajar siswa."
        :close-url="route($operationRoutePrefix.'.class-group-memberships.show', $classGroupMembership)"
    >
        @include('superadmin.class-group-memberships._form', ['classGroupMembership' => $classGroupMembership])
    </x-dashboard.form-panel>
@endsection
