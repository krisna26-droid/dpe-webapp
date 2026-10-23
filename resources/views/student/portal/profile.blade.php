@extends('layouts.dashboard')
@section('title', 'Profil Siswa')
@section('heading', 'Profil Siswa')
@section('description', 'Identitas dan pendaftaran anak.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    @include('student.portal._nav')
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>{{ $profile['name'] }}</h2><p>Hubungi Admin untuk memperbarui data siswa.</p></div></header>
        <div class="dpe-dashboard-card__body">
            <dl class="dpe-portal-details">
                <div><dt>Sekolah</dt><dd>{{ $profile['school'] ?: 'Belum diisi' }}</dd></div>
                <div><dt>Kelas / Grade</dt><dd>{{ $profile['grade'] ?: 'Belum diisi' }}</dd></div>
                <div><dt>Mulai les</dt><dd>{{ $profile['began_on']->locale('id')->translatedFormat('d F Y') }}</dd></div>
                <div><dt>Status siswa</dt><dd>{{ $profile['status'] === 'active' ? 'Aktif' : 'Nonaktif' }}</dd></div>
                <div><dt>Teacher yang ditugaskan</dt><dd>{{ implode(', ', $profile['teachers']) ?: 'Belum ada penugasan aktif' }}</dd></div>
            </dl>
            <h3>Pendaftaran aktif</h3>
            @forelse ($profile['enrollments'] as $enrollment)
                <div class="dpe-portal-item"><strong>{{ $enrollment['program'] }}</strong><small>{{ $enrollment['branch'] }}</small></div>
            @empty
                <p>Belum ada pendaftaran yang berlaku hari ini.</p>
            @endforelse
        </div>
    </article>
@endsection

