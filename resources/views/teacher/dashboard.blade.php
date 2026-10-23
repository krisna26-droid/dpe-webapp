@extends('layouts.dashboard')
@section('title', 'Dashboard Teacher')
@section('heading', 'Dashboard')
@section('description', 'Pantau aktivitas dan laporan siswa Anda.')
@section('content')
    @include('operations._staff-dashboard')
@endsection
