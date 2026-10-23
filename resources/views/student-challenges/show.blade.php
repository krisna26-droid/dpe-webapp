@extends('layouts.dashboard')

@section('title', 'Detail Tantangan Siswa')
@section('heading', 'Detail Tantangan Siswa')
@section('description', 'Periksa catatan tantangan belajar, penanggung jawab, dan sesi yang terkait.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Pendampingan</p>
            <h2>{{ $studentChallenge->category_name }}</h2>
            <p>{{ $studentChallenge->student?->full_name ?: 'Siswa belum tersedia' }} · {{ $studentChallenge->logged_on?->format('d M Y') ?: 'Tanggal belum tersedia' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.student-challenges.edit', $studentChallenge) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit tantangan</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.student-challenges.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    @php
        $teacherName = $studentChallenge->teacher?->user?->full_name
            ?: ($studentChallenge->teacher?->user?->username ?: 'Guru tidak ditemukan');
    @endphp

    <section class="dpe-dashboard-grid dpe-dashboard-grid--two">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Informasi tantangan</h2>
                    <p>Data utama catatan pendampingan siswa.</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="report" /> Tantangan</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Siswa</p>
                        <p class="dpe-dashboard-detail__value">{{ $studentChallenge->student?->full_name ?: '—' }}</p>
                        @if ($studentChallenge->student?->grade_name)
                            <p class="dpe-dashboard-field__hint">{{ $studentChallenge->student->grade_name }}</p>
                        @endif
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Guru pencatat</p>
                        <p class="dpe-dashboard-detail__value">{{ $teacherName }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Tanggal pencatatan</p>
                        <p class="dpe-dashboard-detail__value">{{ $studentChallenge->logged_on?->format('d M Y') ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Kategori tantangan</p>
                        <p class="dpe-dashboard-detail__value">{{ $studentChallenge->category_name }}</p>
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Catatan internal</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal" style="white-space: pre-line;">{{ $studentChallenge->internal_note ?: 'Tidak ada catatan tambahan.' }}</p>
                    </div>
                </div>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Sesi terkait</h2>
                    <p>Referensi pertemuan belajar jika tantangan dicatat dari sesi tertentu.</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="calendar" /> Referensi</span>
            </header>
            <div class="dpe-dashboard-card__body">
                @if ($studentChallenge->session)
                    <div class="dpe-dashboard-detail-grid">
                        <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                            <p class="dpe-dashboard-detail__label">Waktu sesi</p>
                            <p class="dpe-dashboard-detail__value">{{ $studentChallenge->session->planned_start_at?->format('d M Y H:i') ?: 'Sesi tercatat' }}</p>
                        </div>
                        <div class="dpe-dashboard-detail">
                            <p class="dpe-dashboard-detail__label">Cabang</p>
                            <p class="dpe-dashboard-detail__value">{{ $studentChallenge->session->branch?->name ?: '—' }}</p>
                        </div>
                        <div class="dpe-dashboard-detail">
                            <p class="dpe-dashboard-detail__label">Program</p>
                            <p class="dpe-dashboard-detail__value">{{ $studentChallenge->session->program?->name ?: '—' }}</p>
                        </div>
                        <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                            <p class="dpe-dashboard-detail__label">Topik sesi</p>
                            <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $studentChallenge->session->topic ?: 'Tidak ada topik sesi.' }}</p>
                        </div>
                    </div>
                @else
                    <x-dashboard.empty
                        title="Tidak ditautkan ke sesi"
                        message="Catatan ini berdiri sendiri dan tidak terhubung ke sesi belajar tertentu."
                        icon="calendar"
                    />
                @endif

                <form method="POST" action="{{ route('superadmin.student-challenges.destroy', $studentChallenge) }}" class="dpe-dashboard-form-actions" data-confirm-form data-confirm-title="Hapus tantangan siswa ini?" data-confirm-message="Catatan tantangan ini akan dihapus dari pendampingan siswa. Pastikan data yang dipilih sudah benar." data-confirm-action="Hapus tantangan">
                    @csrf
                    @method('DELETE')
                    <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit">
                        <x-dashboard.icon name="trash" />
                        <span>Hapus tantangan</span>
                    </button>
                </form>
            </div>
        </article>
    </section>
@endsection
