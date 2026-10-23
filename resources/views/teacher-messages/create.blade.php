@extends('layouts.dashboard')

@section('title', 'Tulis Pesan Guru')
@section('heading', 'Pesan Guru')
@section('description', 'Catat pesan guru untuk siswa dan hubungkan dengan sesi belajar jika diperlukan.')

@section('content')
    <x-dashboard.form-panel
        title="Tulis pesan guru"
        description="Pilih siswa dan guru, lalu tulis isi pesan yang perlu disimpan dalam riwayat pembelajaran."
        :close-url="route('superadmin.teacher-messages.index')"
    >
        @include('teacher-messages._form')
    </x-dashboard.form-panel>
@endsection
