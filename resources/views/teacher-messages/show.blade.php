@php
    $teacherName = $teacherMessage->teacher?->user?->full_name
        ?: ($teacherMessage->teacher?->user?->username ?: 'Guru tidak ditemukan');
    $studentName = $teacherMessage->student?->full_name ?: 'Siswa tidak ditemukan';
@endphp

@extends('layouts.dashboard')

@section('title', 'Detail Pesan Guru')
@section('heading', 'Detail Pesan Guru')
@section('description', 'Periksa isi komunikasi, penerima, pengirim, dan relasi sesi belajar.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Komunikasi</p>
            <h2>{{ $studentName }}</h2>
            <p>{{ $teacherName }} · {{ $teacherMessage->created_at?->format('d M Y H:i') ?: 'Waktu belum tersedia' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.teacher-messages.edit', $teacherMessage) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit pesan</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.teacher-messages.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--two">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Isi pesan</h2>
                    <p>Catatan komunikasi yang tersimpan untuk siswa.</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="bell" /> Pesan guru</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Isi komunikasi</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal" style="white-space: pre-line;">{{ $teacherMessage->message_body }}</p>
                    </div>
                </div>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Relasi pembelajaran</h2>
                    <p>Siswa, guru, sesi, dan lampiran yang terkait.</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="book" /> Referensi</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Siswa</p>
                        <p class="dpe-dashboard-detail__value">{{ $studentName }}</p>
                        @if ($teacherMessage->student?->portalUser?->username)
                            <p class="dpe-dashboard-field__hint">{{ $teacherMessage->student->portalUser->username }}</p>
                        @endif
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Guru</p>
                        <p class="dpe-dashboard-detail__value">{{ $teacherName }}</p>
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Sesi belajar</p>
                        @if ($teacherMessage->session)
                            <p class="dpe-dashboard-detail__value">{{ $teacherMessage->session->planned_start_at?->format('d M Y H:i') ?: 'Sesi tercatat' }}</p>
                            <p class="dpe-dashboard-field__hint">
                                {{ $teacherMessage->session->branch?->name ?: 'Cabang belum tersedia' }}
                                · {{ $teacherMessage->session->program?->name ?: ($teacherMessage->session->topic ?: 'Sesi belajar') }}
                            </p>
                        @else
                            <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">Tidak ditautkan ke sesi tertentu.</p>
                        @endif
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Lampiran</p>
                        @if ($teacherMessage->attachment)
                            <p class="dpe-dashboard-detail__value">{{ $teacherMessage->attachment->original_name ?: 'File tanpa nama' }}</p>
                            <p class="dpe-dashboard-field__hint">
                                {{ $teacherMessage->attachment->mime_type ?: 'Tipe file tidak tersedia' }}
                                · {{ number_format((int) $teacherMessage->attachment->size_bytes) }} byte
                            </p>
                        @else
                            <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">Tidak ada lampiran.</p>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('superadmin.teacher-messages.destroy', $teacherMessage) }}" class="dpe-dashboard-form-actions" data-confirm-form data-confirm-title="Hapus pesan guru ini?" data-confirm-message="Riwayat komunikasi ini akan dihapus dari aplikasi. Pastikan pesan yang dipilih memang boleh dihapus." data-confirm-action="Hapus pesan">
                    @csrf
                    @method('DELETE')
                    <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit">
                        <x-dashboard.icon name="trash" />
                        <span>Hapus pesan</span>
                    </button>
                </form>
            </div>
        </article>
    </section>
@endsection
