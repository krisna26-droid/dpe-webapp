@extends('layouts.dashboard')

@section('title', 'Video Belajar')
@section('heading', 'Video Belajar')
@section('description', 'Kelola dokumentasi video belajar siswa yang dibuat oleh guru.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Materi</p>
            <h2>Video belajar</h2>
            <p>Catat video pembelajaran berdasarkan siswa, guru, tanggal, topik, dan sesi yang terkait.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.learning-videos.create') }}">
                <x-dashboard.icon name="video" />
                <span>Tambah video</span>
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
                <h2>Daftar video belajar</h2>
                <p>{{ $learningVideos->total() }} video tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="video" /> Materi siswa</span>
        </header>

        @if ($learningVideos->count() === 0)
            <x-dashboard.empty
                title="Belum ada video belajar"
                message="Video akan tampil setelah guru atau admin mencatat dokumentasi pembelajaran siswa."
                icon="video"
                :action-url="route('superadmin.learning-videos.create')"
                action-label="Tambah video"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Siswa</th>
                            <th>Guru</th>
                            <th>Topik</th>
                            <th>Sesi terkait</th>
                            <th>Video</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($learningVideos as $learningVideo)
                            @php
                                $teacherName = $learningVideo->teacher?->user?->full_name ?: ($learningVideo->teacher?->user?->username ?: '—');
                            @endphp
                            <tr>
                                <td><a href="{{ route('superadmin.learning-videos.show', $learningVideo) }}"><strong>{{ $learningVideo->video_on?->format('d M Y') ?: '—' }}</strong></a></td>
                                <td>{{ $learningVideo->student?->full_name ?: 'Siswa tidak ditemukan' }}</td>
                                <td>{{ $teacherName }}</td>
                                <td><strong>{{ $learningVideo->topic }}</strong></td>
                                <td>
                                    @if ($learningVideo->session)
                                        {{ $learningVideo->session->planned_start_at?->format('d M Y H:i') ?: 'Sesi tercatat' }}<br>
                                        <span>{{ $learningVideo->session->topic ?: 'Tanpa topik sesi' }}</span>
                                    @else
                                        <span>Tidak ditautkan</span>
                                    @endif
                                </td>
                                <td>@if (\App\Services\StudentProgressService::safeUrl($learningVideo->video_url))
                            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ $learningVideo->video_url }}" target="_blank" rel="noopener noreferrer"><x-dashboard.icon name="arrow" /> Buka</a>
                        @else
                            <span class="dpe-dashboard-field__hint">Tautan tidak valid; perbaiki URL melalui Edit.</span>
                        @endif</td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.learning-videos.edit', $learningVideo) }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$learningVideos" />
        @endif
    </article>
@endsection
