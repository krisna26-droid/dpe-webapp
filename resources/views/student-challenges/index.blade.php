@extends('layouts.dashboard')

@section('title', 'Tantangan Siswa')
@section('heading', 'Tantangan Siswa')
@section('description', 'Catat area yang perlu dilatih siswa dari proses belajar bersama guru.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Pendampingan</p>
            <h2>Tantangan siswa</h2>
            <p>Kelola catatan tantangan belajar yang perlu dipantau dan dilatih kembali.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.student-challenges.create') }}">
                <x-dashboard.icon name="plus" />
                <span>Tambah tantangan</span>
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
                <h2>Daftar tantangan siswa</h2>
                <p>{{ $studentChallenges->total() }} catatan tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="report" /> Pendampingan</span>
        </header>

        @if ($studentChallenges->count() === 0)
            <x-dashboard.empty
                title="Belum ada tantangan siswa"
                message="Catatan tantangan akan muncul setelah admin atau guru mencatat area belajar yang perlu dilatih."
                icon="report"
                :action-url="route('superadmin.student-challenges.create')"
                action-label="Tambah tantangan"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Siswa</th>
                            <th>Guru</th>
                            <th>Kategori tantangan</th>
                            <th>Sesi terkait</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($studentChallenges as $studentChallenge)
                            @php
                                $teacherName = $studentChallenge->teacher?->user?->full_name
                                    ?: ($studentChallenge->teacher?->user?->username ?: 'Guru tidak ditemukan');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('superadmin.student-challenges.show', $studentChallenge) }}">
                                        <strong>{{ $studentChallenge->logged_on?->format('d M Y') ?: '—' }}</strong>
                                    </a>
                                </td>
                                <td>
                                    <strong>{{ $studentChallenge->student?->full_name ?: 'Siswa tidak ditemukan' }}</strong>
                                    @if ($studentChallenge->student?->grade_name)
                                        <br><span>{{ $studentChallenge->student->grade_name }}</span>
                                    @endif
                                </td>
                                <td>{{ $teacherName }}</td>
                                <td><strong>{{ $studentChallenge->category_name }}</strong></td>
                                <td>
                                    @if ($studentChallenge->session)
                                        {{ $studentChallenge->session->planned_start_at?->format('d M Y H:i') ?: 'Sesi tercatat' }}<br>
                                        <span>{{ $studentChallenge->session->topic ?: ($studentChallenge->session->branch?->name ?: 'Sesi belajar') }}</span>
                                    @else
                                        <span>Tidak ditautkan</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="dpe-dashboard-table-actions">
                                        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-challenges.show', $studentChallenge) }}">Detail</a>
                                        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-challenges.edit', $studentChallenge) }}">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$studentChallenges" />
        @endif
    </article>
@endsection
