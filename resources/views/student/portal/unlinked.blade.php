@extends('layouts.dashboard')
@section('title', 'Portal Siswa')
@section('heading', 'Portal Siswa / Parent')
@section('description', 'Informasi belajar untuk satu anak.')
@section('content')
    <x-dashboard.empty title="Akun belum terhubung dengan siswa" message="Hubungi Admin agar akun ini dihubungkan dengan profil siswa melalui menu Siswa & Pendaftaran. Setelah terhubung, jadwal, pembayaran, dan laporan anak akan tampil di sini." icon="student" />
@endsection

