@extends('layouts.dashboard')

@section('title', 'Edit Kompetensi')
@section('heading', 'Kompetensi & Skill')
@section('description', 'Perbarui nama, kode, urutan, atau status kompetensi.')

@section('content')
    <x-dashboard.form-panel
        title="Edit kompetensi"
        description="Perubahan akan digunakan untuk pengisian laporan berikutnya."
        :close-url="route('superadmin.learning-skills.show', $learningSkill)"
    >
        @include('learning-skills._form')
    </x-dashboard.form-panel>
@endsection
