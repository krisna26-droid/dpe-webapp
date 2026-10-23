@extends('layouts.dashboard')

@section('title', 'Tambah Tagihan Bulanan')
@section('heading', 'Tagihan Bulanan')
@section('description', 'Buat tagihan baru untuk seorang siswa pada periode tertentu.')

@section('content')
    <x-dashboard.form-panel
        title="Tambah tagihan bulanan"
        description="Pilih siswa, cabang, periode tagihan, nominal, dan jatuh tempo pembayaran."
        :close-url="route('superadmin.monthly-charges.index')"
    >
        @include('monthly-charges._form')
    </x-dashboard.form-panel>
@endsection
