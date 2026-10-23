@extends('layouts.dashboard')

@section('title', 'Edit Tagihan Bulanan')
@section('heading', 'Tagihan Bulanan')
@section('description', 'Perbarui detail nominal atau periode tagihan yang tersimpan.')

@section('content')
    <x-dashboard.form-panel
        title="Edit tagihan bulanan"
        description="Perubahan akan memperbarui data tagihan siswa pada periode ini."
        :close-url="route('superadmin.monthly-charges.show', $monthlyCharge)"
    >
        @include('monthly-charges._form')
    </x-dashboard.form-panel>
@endsection
