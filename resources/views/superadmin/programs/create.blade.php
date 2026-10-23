@extends('layouts.dashboard')

@section('title', 'Tambah Program Belajar')
@section('heading', 'Program Belajar')
@section('description', 'Tambahkan program yang dapat dipilih pada proses operasional DPE.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah program belajar"
        description="Isi identitas program dan aturan target video bulanannya."
        :close-url="route('superadmin.programs.index')"
    >
        @include('superadmin.programs._form')
    </x-dashboard.form-panel>
@endsection
