@extends('layouts.dashboard')
@section('title', 'Dashboard Super Admin')
@section('heading', 'Dashboard')
@section('description', 'Pantau aktivitas dan laporan seluruh cabang.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/dashboard-overview.css') }}?v={{ filemtime(public_path('assets/dpe/dashboard-overview.css')) }}">
@endpush
@section('content')
    @include('operations._staff-dashboard', ['showDashboardOverview' => true])
@endsection
