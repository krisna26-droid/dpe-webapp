@extends('layouts.dashboard')

@section('title', 'Tambah Tagihan Bulanan')
@section('heading', 'Tagihan Bulanan')
@section('description', 'Buat tagihan baru untuk seorang siswa pada periode tertentu.')

@push('scripts')
    <script src="{{ asset('assets/dpe/monthly-charge-filter.js') }}?v={{ filemtime(public_path('assets/dpe/monthly-charge-filter.js')) }}" defer></script>
@endpush

@section('content')
    @php
        $routePrefix = auth()->user()->role_code === 'admin' ? 'admin' : 'superadmin';
    @endphp
    <x-dashboard.form-panel
        title="Tambah tagihan bulanan"
        description="Saring siswa berdasarkan guru, lalu pilih siswa, cabang, dan periode tagihan."
        :close-url="route($routePrefix.'.monthly-charges.index')"
    >
        @include('monthly-charges._form')
    </x-dashboard.form-panel>
@endsection
