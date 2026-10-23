@extends('layouts.dashboard')

@section('title', 'Kompetensi & Skill')
@section('heading', 'Kompetensi & Skill')
@section('description', 'Kelola kompetensi yang digunakan untuk penilaian perkembangan siswa dan rapor bulanan.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Penilaian</p>
            <h2>Kompetensi dan skill</h2>
            <p>Susun daftar kemampuan belajar yang dapat digunakan guru saat mengisi laporan perkembangan siswa.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.learning-skills.create') }}">
                <x-dashboard.icon name="report" />
                <span>Tambah skill</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar kompetensi</h2>
                <p>{{ $learningSkills->total() }} skill tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="report" /> Acuan rapor</span>
        </header>

        @if ($learningSkills->count() === 0)
            <x-dashboard.empty
                title="Belum ada kompetensi"
                message="Tambahkan skill seperti Listening, Speaking, Reading, atau Writing agar dapat dipakai dalam laporan siswa."
                icon="report"
                :action-url="route('superadmin.learning-skills.create')"
                action-label="Tambah skill"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Urutan</th>
                            <th>Kode</th>
                            <th>Nama kompetensi</th>
                            <th>Penggunaan rapor</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($learningSkills as $learningSkill)
                            <tr>
                                <td><strong>{{ $learningSkill->display_order }}</strong></td>
                                <td><a href="{{ route('superadmin.learning-skills.show', $learningSkill) }}"><strong>{{ $learningSkill->code }}</strong></a></td>
                                <td><a href="{{ route('superadmin.learning-skills.show', $learningSkill) }}"><strong>{{ $learningSkill->name }}</strong></a></td>
                                <td>{{ $learningSkill->report_skills_count }} laporan</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $learningSkill->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">
                                        {{ $learningSkill->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.learning-skills.edit', $learningSkill) }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$learningSkills" />
        @endif
    </article>
@endsection
