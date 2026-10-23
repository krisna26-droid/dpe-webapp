@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@extends('layouts.dashboard')

@section('title', 'Edit Sesi Belajar')
@section('heading', 'Sesi Belajar')
@section('description', 'Perbarui jadwal dan catatan sesi belajar.')

@section('content')
    <x-dashboard.form-panel
        title="Edit sesi belajar"
        description="Perbarui jadwal dan catatan sesi belajar."
        :close-url="route($operationRoutePrefix.'.lesson-sessions.show', $lessonSession)"
    >
        @include('superadmin.lesson-sessions._form')
    </x-dashboard.form-panel>
@endsection
