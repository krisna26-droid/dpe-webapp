@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@extends('layouts.dashboard')

@section('title', 'Tambah Kelompok Kelas')
@section('heading', 'Kelompok Kelas')
@section('description', 'Hubungkan program belajar dengan cabang dan Teacher.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah kelompok kelas"
        description="Tentukan identitas kelompok, program, cabang, dan Teacher utama."
        :close-url="route($operationRoutePrefix.'.class-groups.index')"
    >
        @include('superadmin.class-groups._form')
    </x-dashboard.form-panel>
@endsection
