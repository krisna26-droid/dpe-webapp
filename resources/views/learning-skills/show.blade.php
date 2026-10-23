@php
    $statusLabel = $learningSkill->is_active ? 'Aktif' : 'Nonaktif';
@endphp

@extends('layouts.dashboard')

@section('title', $learningSkill->name)
@section('heading', 'Detail Kompetensi')
@section('description', 'Periksa konfigurasi dan penggunaan kompetensi pada rapor siswa.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Penilaian</p>
            <h2>{{ $learningSkill->name }}</h2>
            <p>{{ $learningSkill->code }} · Urutan {{ $learningSkill->display_order }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.learning-skills.edit', $learningSkill) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit skill</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.learning-skills.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--two">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Konfigurasi skill</h2>
                    <p>Identitas yang tampil pada modul penilaian siswa.</p>
                </div>
                <span class="dpe-dashboard-badge {{ $learningSkill->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">{{ $statusLabel }}</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Kode skill</p>
                        <p class="dpe-dashboard-detail__value">{{ $learningSkill->code }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Nama kompetensi</p>
                        <p class="dpe-dashboard-detail__value">{{ $learningSkill->name }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Urutan tampil</p>
                        <p class="dpe-dashboard-detail__value">{{ $learningSkill->display_order }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Dipakai pada laporan</p>
                        <p class="dpe-dashboard-detail__value">{{ $learningSkill->report_skills_count }} laporan</p>
                    </div>
                </div>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Status penggunaan</h2>
                    <p>Skill nonaktif tidak disarankan untuk laporan baru.</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="report" /> Rapor</span>
            </header>
            <div class="dpe-dashboard-card__body">
                @if ($learningSkill->is_active)
                    <x-dashboard.empty
                        title="Skill aktif"
                        message="Guru dapat memilih skill ini ketika mengisi laporan perkembangan siswa."
                        icon="check"
                    />
                @else
                    <x-dashboard.empty
                        title="Skill nonaktif"
                        message="Riwayat laporan tetap tersimpan, tetapi skill ini sebaiknya tidak dipakai pada laporan baru."
                        icon="warning"
                    />
                @endif
                <form method="POST" action="{{ route('superadmin.learning-skills.destroy', $learningSkill) }}" class="dpe-dashboard-form-actions" data-confirm-form data-confirm-title="Hapus kompetensi ini?" data-confirm-message="Riwayat laporan yang sudah tersimpan dapat terdampak. Pastikan skill ini memang boleh dihapus." data-confirm-action="Hapus skill">
                    @csrf
                    @method('DELETE')
                    <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit">
                        <x-dashboard.icon name="trash" />
                        <span>Hapus skill</span>
                    </button>
                </form>
            </div>
        </article>
    </section>
@endsection
