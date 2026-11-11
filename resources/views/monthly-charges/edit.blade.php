@extends('layouts.dashboard')

@section('title', 'Edit Tagihan Bulanan')
@section('heading', 'Tagihan Bulanan')
@section('description', 'Perbarui detail nominal atau periode tagihan yang tersimpan.')

@section('content')
    @php
        $routePrefix = auth()->user()->role_code === 'admin' ? 'admin' : 'superadmin';
    @endphp
    <x-dashboard.form-panel
        title="Edit tagihan bulanan"
        description="Perubahan akan memperbarui data tagihan siswa pada periode ini."
        :close-url="route($routePrefix.'.monthly-charges.show', $monthlyCharge)"
    >
        @include('monthly-charges._form')
    </x-dashboard.form-panel>
@endsection
