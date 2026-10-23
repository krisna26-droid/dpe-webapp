@extends('layouts.dashboard')
@section('title', 'Jadwal Mengajar')
@section('heading', 'Jadwal Mengajar')
@section('description', 'Pertemuan kelas dan private yang ditugaskan kepada Anda.')
@push('head') @include('lesson-series._head') @endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Teacher / Kelas Saya</p><h2>Siapkan pertemuan belajar Anda.</h2><p>Buka kartu untuk melihat peserta dan menuju absensi siswa.</p></div>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.availability.create') }}"><x-dashboard.icon name="plus" />Tambah slot tersedia</a>
    </section>
    @include('teacher.workspace._tabs')
    <div class="dpe-teacher-stack">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Filter jadwal</h2><p>Tanggal mengikuti waktu pelaksanaan jika sudah dicatat.</p></div></header>
            <div class="dpe-dashboard-card__body">
                <form method="GET" action="{{ route('teacher.schedule.index') }}" class="dpe-teacher-filters">
                    <div class="dpe-dashboard-field"><label for="schedule-from">Dari tanggal</label><input id="schedule-from" name="from" type="date" value="{{ $filters['from'] }}" min="2000-01-01" max="2100-12-31"></div>
                    <div class="dpe-dashboard-field"><label for="schedule-to">Sampai tanggal</label><input id="schedule-to" name="to" type="date" value="{{ $filters['to'] }}" min="2000-01-01" max="2100-12-31"></div>
                    <div class="dpe-dashboard-field"><label for="schedule-branch">Cabang</label><select id="schedule-branch" name="branch_id"><option value="">Semua cabang saya</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="dpe-dashboard-field"><label for="schedule-program">Program</label><select id="schedule-program" name="program_id"><option value="">Semua program saya</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') === $program->id)>{{ $program->name }}</option>@endforeach</select></div>
                    <div class="dpe-dashboard-field"><label for="schedule-status">Status</label><select id="schedule-status" name="status"><option value="">Semua status</option>@foreach(['scheduled'=>'Terjadwal','ongoing'=>'Berlangsung','completed'=>'Selesai','cancelled'=>'Dibatalkan','rescheduled'=>'Memiliki pengganti'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div class="dpe-dashboard-field"><label for="schedule-q">Kelas / topik</label><input id="schedule-q" name="q" type="search" maxlength="120" placeholder="Cari kelas, program, atau topik" value="{{ $filters['q'] ?? '' }}"></div>
                    <div class="dpe-teacher-filter-actions"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.schedule.index') }}">Reset</a></div>
                </form>
                <div class="dpe-teacher-quick"><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('teacher.schedule.index', ['from'=>today()->toDateString(), 'to'=>today()->toDateString()]) }}">Hari ini</a><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('teacher.schedule.index', ['from'=>today()->startOfWeek(1)->toDateString(), 'to'=>today()->startOfWeek(1)->addDays(6)->toDateString()]) }}">Minggu ini</a><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('teacher.schedule.index', ['from'=>today()->startOfMonth()->toDateString(), 'to'=>today()->endOfMonth()->toDateString()]) }}">Bulan ini</a></div>
            </div>
        </article>
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Pertemuan saya</h2><p>Jadwal ditetapkan oleh Admin atau Super Admin.</p></div><span class="dpe-dashboard-badge">{{ $sessions->total() }} pertemuan</span></header>
            @if($rows->isEmpty())
                <x-dashboard.empty title="Belum ada jadwal untuk filter ini" message="Coba tanggal atau filter lain. Jika sesi belum ditugaskan, hubungi Admin cabang." icon="calendar" />
            @else
                <div class="dpe-dashboard-card__body">
                    @foreach($rows->groupBy(fn($row) => $row['meetingAt']->toDateString()) as $dayRows)
                    <section class="dpe-teacher-date-group"><h3>{{ $dayRows->first()['meetingAt']->locale('id')->translatedFormat('l, d F Y') }}</h3><div class="dpe-teacher-sessions">
                    @foreach($dayRows as $row)
                        <a class="dpe-teacher-session" href="{{ route('teacher.schedule.show', $row['session']) }}">
                            <div class="dpe-teacher-session__head"><strong>{{ $row['meetingAt']->locale('id')->translatedFormat('l, d M Y') }}</strong><span class="dpe-dashboard-badge {{ in_array($row['statusKey'], ['cancelled','rescheduled']) ? 'dpe-dashboard-badge--warning' : 'dpe-dashboard-badge--success' }}">{{ $row['status'] }}</span></div>
                            <div><h3>{{ $row['session']->classGroup?->name ?: 'Private / tanpa kelompok' }}</h3><p>{{ $row['session']->program?->name ?: 'Program belum tersedia' }}</p></div>
                            <div class="dpe-teacher-session__meta">
                                <span><x-dashboard.icon name="clock" />{{ $row['meetingAt']->format('H:i') }}–{{ $row['endsAt']->format('H:i') }} · {{ $row['meetingAt']->timezoneName }}</span>
                                <span><x-dashboard.icon name="building" />{{ $row['session']->branch?->name }} · {{ $row['session']->isAtStudentHome() ? 'Rumah siswa' : 'Di cabang' }}</span>
                                <span><x-dashboard.icon name="users" />{{ $row['participants']->count() }} siswa</span>
                            </div>
                            <span class="dpe-teacher-session__open">Lihat kelas dan peserta<x-dashboard.icon name="arrow" /></span>
                        </a>
                    @endforeach
                    </div></section>
                    @endforeach
                </div><x-dashboard.pagination :paginator="$sessions" />
            @endif
        </article>
    </div>
@endsection
