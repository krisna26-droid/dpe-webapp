@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@extends('layouts.dashboard')

@section('title', 'Tambah Sesi Belajar')
@section('heading', 'Sesi Belajar')
@section('description', 'Atur program, guru, dan waktu pertemuan siswa.')
@push('head') @include('lesson-series._head') @endpush

@section('content')
    <x-dashboard.session-mode :prefix="$operationRoutePrefix" />
    <x-dashboard.form-panel
        title="Tambah sesi belajar"
        description="Atur program, guru, dan waktu pertemuan siswa."
        :close-url="route($operationRoutePrefix.'.lesson-sessions.index')"
    >
        @include('superadmin.lesson-sessions._form')
    </x-dashboard.form-panel>
@endsection
