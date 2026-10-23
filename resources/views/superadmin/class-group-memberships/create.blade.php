@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@extends('layouts.dashboard')

@section('title', 'Tambah Anggota Kelompok')
@section('heading', 'Kelompok Kelas')
@section('description', 'Hubungkan siswa dengan kelompok dan periode belajar.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah anggota kelompok"
        description="Pilih kelompok, siswa, dan periode keanggotaan yang berlaku."
        :close-url="route($operationRoutePrefix.'.class-group-memberships.index')"
    >
        @include('superadmin.class-group-memberships._form')
    </x-dashboard.form-panel>
@endsection
