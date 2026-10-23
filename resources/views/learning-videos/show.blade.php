@php
    $teacherName = $learningVideo->teacher?->user?->full_name ?: ($learningVideo->teacher?->user?->username ?: 'Guru');
@endphp

@extends('layouts.dashboard')

@section('title', 'Detail Video Belajar')
@section('heading', 'Detail Video Belajar')
@section('description', 'Periksa dokumentasi video, siswa, guru, dan sesi pembelajaran.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Materi</p>
            <h2>{{ $learningVideo->topic }}</h2>
            <p>{{ $learningVideo->student?->full_name ?: 'Siswa belum tersedia' }} · {{ $learningVideo->video_on?->format('d M Y') ?: 'Tanggal belum tersedia' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.learning-videos.edit', $learningVideo) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit video</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.learning-videos.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--two">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Informasi video</h2>
                    <p>Data utama dokumentasi pembelajaran siswa.</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="video" /> Materi</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Siswa</p>
                        <p class="dpe-dashboard-detail__value">{{ $learningVideo->student?->full_name ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Guru</p>
                        <p class="dpe-dashboard-detail__value">{{ $teacherName }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Tanggal video</p>
                        <p class="dpe-dashboard-detail__value">{{ $learningVideo->video_on?->format('d M Y') ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Topik</p>
                        <p class="dpe-dashboard-detail__value">{{ $learningVideo->topic }}</p>
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Deskripsi</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $learningVideo->description ?: 'Tidak ada deskripsi.' }}</p>
                    </div>
                </div>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Sesi dan tautan video</h2>
                    <p>Referensi sesi belajar dan akses ke video yang disimpan.</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="calendar" /> Referensi</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Sesi terkait</p>
                        @if ($learningVideo->session)
                            <p class="dpe-dashboard-detail__value">{{ $learningVideo->session->planned_start_at?->format('d M Y H:i') ?: 'Sesi tercatat' }}</p>
                            <p class="dpe-dashboard-field__hint">{{ $learningVideo->session->branch?->name ?: 'Cabang belum tersedia' }} · {{ $learningVideo->session->program?->name ?: 'Program belum tersedia' }}</p>
                        @else
                            <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">Tidak ditautkan ke sesi tertentu.</p>
                        @endif
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Tautan video</p>
                        @if (\App\Services\StudentProgressService::safeUrl($learningVideo->video_url))
                            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ $learningVideo->video_url }}" target="_blank" rel="noopener noreferrer"><x-dashboard.icon name="arrow" /> Buka video</a>
                        @else
                            <span class="dpe-dashboard-field__hint">Tautan tidak valid; perbaiki URL melalui Edit.</span>
                        @endif
                    </div>
                </div>
                <form method="POST" action="{{ route('superadmin.learning-videos.destroy', $learningVideo) }}" class="dpe-dashboard-form-actions" data-confirm-form data-confirm-title="Hapus video belajar ini?" data-confirm-message="Data video ini akan dihapus dari dokumentasi pembelajaran. Pastikan data yang dipilih sudah benar." data-confirm-action="Hapus video">
                    @csrf
                    @method('DELETE')
                    <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit">
                        <x-dashboard.icon name="trash" />
                        <span>Hapus video</span>
                    </button>
                </form>
            </div>
        </article>
    </section>
@endsection
