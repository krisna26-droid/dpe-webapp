@extends('layouts.dashboard')

@section('title', 'Pesan Guru')
@section('heading', 'Pesan Guru')
@section('description', 'Kelola pesan yang dikirimkan guru untuk membantu tindak lanjut belajar siswa.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Komunikasi</p>
            <h2>Pesan guru</h2>
            <p>Catat komunikasi guru kepada siswa, hubungkan dengan sesi belajar, dan simpan lampiran jika tersedia.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.teacher-messages.create') }}">
                <x-dashboard.icon name="bell" />
                <span>Tulis pesan</span>
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
                <h2>Riwayat pesan guru</h2>
                <p>{{ $teacherMessages->total() }} pesan tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="bell" /> Komunikasi siswa</span>
        </header>

        @if ($teacherMessages->count() === 0)
            <x-dashboard.empty
                title="Belum ada pesan guru"
                message="Pesan akan tampil setelah admin mencatat komunikasi guru dengan siswa."
                icon="bell"
                :action-url="route('superadmin.teacher-messages.create')"
                action-label="Tulis pesan pertama"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Siswa</th>
                            <th>Guru</th>
                            <th>Isi pesan</th>
                            <th>Sesi</th>
                            <th>Lampiran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teacherMessages as $teacherMessage)
                            @php
                                $teacherName = $teacherMessage->teacher?->user?->full_name
                                    ?: ($teacherMessage->teacher?->user?->username ?: 'Guru tidak ditemukan');
                                $messagePreview = \Illuminate\Support\Str::limit($teacherMessage->message_body, 96);
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('superadmin.teacher-messages.show', $teacherMessage) }}">
                                        <strong>{{ $teacherMessage->created_at?->format('d M Y') ?: '—' }}</strong>
                                    </a>
                                    <br><span>{{ $teacherMessage->created_at?->format('H:i') ?: 'Waktu belum tersedia' }}</span>
                                </td>
                                <td>{{ $teacherMessage->student?->full_name ?: 'Siswa tidak ditemukan' }}</td>
                                <td>{{ $teacherName }}</td>
                                <td><span>{{ $messagePreview }}</span></td>
                                <td>
                                    @if ($teacherMessage->session)
                                        {{ $teacherMessage->session->planned_start_at?->format('d M Y H:i') ?: 'Sesi tercatat' }}<br>
                                        <span>{{ $teacherMessage->session->topic ?: ($teacherMessage->session->branch?->name ?: 'Sesi belajar') }}</span>
                                    @else
                                        <span>Tidak ditautkan</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($teacherMessage->attachment)
                                        <span class="dpe-dashboard-badge dpe-dashboard-badge--success"><x-dashboard.icon name="report" /> Tersedia</span>
                                    @else
                                        <span>—</span>
                                    @endif
                                </td>
                                <td>
                                    <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.teacher-messages.show', $teacherMessage) }}">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$teacherMessages" />
        @endif
    </article>
@endsection
