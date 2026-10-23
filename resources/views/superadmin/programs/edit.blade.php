@extends('layouts.dashboard')

@section('title', 'Edit Program Belajar')
@section('heading', 'Program Belajar')
@section('description', 'Perbarui identitas dan pengaturan program belajar.')

@section('content')
    <x-dashboard.form-panel
        title="Edit program belajar"
        description="Perubahan program akan digunakan pada data operasional berikutnya."
        :close-url="route('superadmin.programs.show', $program)"
    >
        @include('superadmin.programs._form')
    </x-dashboard.form-panel>
@endsection
