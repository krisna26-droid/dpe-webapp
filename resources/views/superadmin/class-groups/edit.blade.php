@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@extends('layouts.dashboard')

@section('title', 'Edit Kelompok Kelas')
@section('heading', 'Kelompok Kelas')
@section('description', 'Perbarui identitas dan penanggung jawab kelompok kelas.')

@section('content')
    <x-dashboard.form-panel
        title="Edit kelompok kelas"
        description="Perubahan ini menjadi referensi untuk peserta dan sesi belajar berikutnya."
        :close-url="route($operationRoutePrefix.'.class-groups.show', $classGroup)"
    >
        @include('superadmin.class-groups._form', ['classGroup' => $classGroup])
    </x-dashboard.form-panel>
@endsection
