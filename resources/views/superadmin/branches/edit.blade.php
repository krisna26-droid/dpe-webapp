@extends('layouts.dashboard')

@section('title', 'Edit Cabang')
@section('heading', 'Cabang')
@section('description', 'Perbarui informasi cabang dan tanggal rekap pembayaran.')

@section('content')
    <x-dashboard.form-panel
        title="Edit cabang"
        description="Perbarui informasi cabang dan tanggal rekap pembayaran."
        :close-url="route('superadmin.branches.show', $branch)"
    >
        @include('superadmin.branches._form')
    </x-dashboard.form-panel>
@endsection
