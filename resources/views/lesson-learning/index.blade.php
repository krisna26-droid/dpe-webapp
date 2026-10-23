@extends('layouts.dashboard')

@section('title', 'Catatan Belajar')
@section('heading', 'Catatan Belajar')
@section('description', 'Topik, materi, aktivitas, dan catatan siswa pada setiap pertemuan.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/lesson-learning.css') }}?v={{ filemtime(public_path('assets/dpe/lesson-learning.css')) }}">
@endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Pembelajaran / Pertemuan</p>
            <h2>Catat proses belajar setiap sesi.</h2>
            <p>Pilih pertemuan untuk melengkapi ringkasan dan catatan individual siswa.</p>
        </div>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route(str_replace('learning-notes', 'student-attendance', $routeBase).'.index') }}"><x-dashboard.icon name="student" />Buka absensi siswa</a>
    </section>

    <article class="dpe-dashboard-card dpe-lesson-learning-filters">
        <header class="dpe-dashboard-card__head"><div><h2>Filter pertemuan</h2><p>Jadwal mengikuti akses cabang dan penugasan Anda.</p></div></header>
        <div class="dpe-dashboard-card__body">
            <form method="GET" action="{{ route($routeBase.'.index') }}" class="dpe-dashboard-form">
                <div class="dpe-dashboard-form-grid dpe-dashboard-form-grid--three">
                    <div class="dpe-dashboard-field">
                        <label for="learning-month">Bulan pertemuan</label>
                        <input id="learning-month" name="month" type="month" value="{{ $filters['month'] }}">
                        @error('month')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                    </div>
                    <div class="dpe-dashboard-field">
                        <label for="learning-branch">Cabang</label>
                        <select id="learning-branch" name="branch_id">
                            <option value="">Semua cabang saya</option>
                            @foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') === $branch->id)>{{ $branch->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="dpe-dashboard-field">
                        <label for="learning-group">Kelas / private</label>
                        <select id="learning-group" name="class_group_id">
                            <option value="">Semua pertemuan</option>
                            <option value="private" @selected(($filters['class_group_id'] ?? '') === 'private')>Private / tanpa kelompok</option>
                            @foreach ($groups as $group)<option value="{{ $group->id }}" @selected(($filters['class_group_id'] ?? '') === $group->id)>{{ $group->name }} · {{ $group->branch?->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="dpe-dashboard-field">
                        <label for="learning-completion">Ringkasan pertemuan</label>
                        <select id="learning-completion" name="completion">
                            <option value="">Semua catatan</option>
                            <option value="complete" @selected(($filters['completion'] ?? '') === 'complete')>Topik, materi, aktivitas lengkap</option>
                            <option value="pending" @selected(($filters['completion'] ?? '') === 'pending')>Masih perlu dilengkapi</option>
                        </select>
                    </div>
                    <div class="dpe-dashboard-field">
                        <label for="learning-search">Cari pertemuan</label>
                        <input id="learning-search" name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Topik, kelas, program, atau cabang">
                    </div>
                    <div class="dpe-lesson-learning-filter-actions">
                        <button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Tampilkan</button>
                        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index') }}">Reset</a>
                    </div>
                </div>
            </form>
        </div>
    </article>

    <section class="dpe-dashboard-grid dpe-lesson-learning-metrics" aria-label="Ringkasan catatan pertemuan">
        <x-dashboard.metric-card label="Pertemuan" :value="$total" :meta="$month->locale('id')->translatedFormat('F Y')" icon="calendar" />
        <x-dashboard.metric-card label="Ringkasan lengkap" :value="$complete" meta="Topik, materi, dan aktivitas terisi" tone="green" icon="check" />
        <x-dashboard.metric-card label="Perlu dilengkapi" :value="$pending" meta="Termasuk jadwal yang belum berlangsung" tone="orange" icon="book" />
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Daftar pertemuan</h2><p>Absensi dan peserta memakai data pertemuan yang sama dengan menu Absensi Siswa.</p></div><span class="dpe-dashboard-badge">{{ $sessions->total() }} pertemuan</span></header>
        @if ($rows->isEmpty())
            <x-dashboard.empty title="Belum ada pertemuan untuk filter ini" message="Coba bulan atau filter lain. Jadwal dan penugasan sesi ditetapkan oleh Admin atau Super Admin." icon="calendar" />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table dpe-lesson-learning-table">
                    <thead><tr><th>Waktu</th><th>Kelas / Program</th><th>Cabang</th><th>Peserta</th><th>Topik</th><th>Ringkasan</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><strong>{{ $row['meetingAt']->format('d M Y') }}</strong><small>{{ $row['meetingAt']->format('H:i') }} · {{ $row['session']->isAtStudentHome() ? 'Rumah siswa' : 'Di cabang' }}</small></td>
                                <td><strong>{{ $row['session']->classGroup?->name ?: 'Private / tanpa kelompok' }}</strong><small>{{ $row['session']->program?->name }}</small></td>
                                <td>{{ $row['session']->branch?->name }}</td>
                                <td>{{ $row['participants']->count() }} siswa</td>
                                <td>{{ filled($row['session']->topic) ? $row['session']->topic : 'Belum diisi' }}</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $row['filled'] === 3 ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $row['filled'] }} / 3 terisi</span>
                                    @if ($row['blockReason'])<small>{{ $row['session']->isCancelled() ? 'Dibatalkan' : ($row['meetingAt']->isFuture() ? 'Belum berlangsung' : 'Memiliki pengganti') }}</small>@endif
                                </td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($routeBase.'.edit', $row['session']) }}"><x-dashboard.icon name="book" />{{ $row['blockReason'] ? 'Lihat catatan' : 'Buka catatan' }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$sessions" />
        @endif
    </article>
@endsection
