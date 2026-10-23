@php
    $session = $row['session'];
@endphp
@extends('layouts.dashboard')
@section('title', 'Detail Kelas & Pertemuan')
@section('heading', 'Detail Kelas & Pertemuan')
@section('description', 'Jadwal, daftar peserta, dan tindakan pembelajaran pada pertemuan ini.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Teacher / Jadwal Mengajar</p><h2>{{ $session->classGroup?->name ?: 'Private / tanpa kelompok' }}</h2><p>{{ $row['meetingAt']->locale('id')->translatedFormat('l, d F Y') }} · {{ $row['meetingAt']->format('H:i') }}–{{ $row['endsAt']->format('H:i') }}</p></div>
        <div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('teacher.student-attendance.show', $session) }}"><x-dashboard.icon name="student" />Buka Absensi Siswa</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.schedule.index', ['from'=>$month->toDateString(),'to'=>$month->endOfMonth()->toDateString()]) }}"><x-dashboard.icon name="back" />Kembali ke jadwal</a></div>
    </section>
    <div class="dpe-teacher-stack">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Ringkasan pertemuan</h2><p>{{ $session->program?->name }}</p></div><span class="dpe-dashboard-badge">{{ $row['status'] }}</span></header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Cabang</p><p class="dpe-dashboard-detail__value">{{ $session->branch?->name }}</p></div>
                    <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Lokasi</p><p class="dpe-dashboard-detail__value">{{ $session->isAtStudentHome() ? 'Rumah siswa' : 'Di cabang' }}</p></div>
                    <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Jadwal awal</p><p class="dpe-dashboard-detail__value">{{ $session->planned_start_at->format('d M Y H:i') }}–{{ $session->planned_end_at->format('H:i') }}</p></div>
                    <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Pelaksanaan</p><p class="dpe-dashboard-detail__value">{{ $session->actual_start_at ? $row['meetingAt']->format('d M Y H:i').' · '.$row['meetingAt']->timezoneName : 'Belum dicatat' }}</p></div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full"><p class="dpe-dashboard-detail__label">Topik</p><p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $session->topic ?: 'Belum diisi' }}</p></div>
                </div>
                <div class="dpe-teacher-form-actions"><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('teacher.learning-notes.edit', $session) }}"><x-dashboard.icon name="book" />Catatan Belajar</a>@if($row['meetingAt']->isToday() && ! $session->isCancelled() && ! $row['superseded'])<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.attendance.index') }}"><x-dashboard.icon name="clock" />Kehadiran saya hari ini</a>@endif</div>
            </div>
        </article>
        @if($session->rescheduled_from_session_id || $row['superseded'])
            <div class="dpe-teacher-notice"><strong>{{ $row['superseded'] ? 'Pertemuan ini memiliki jadwal pengganti.' : 'Pertemuan ini merupakan jadwal pengganti.' }}</strong><p>Hubungan dengan jadwal asal tetap tersimpan. Absensi mengikuti pertemuan yang berlangsung.</p>@if($related->isNotEmpty())<ul class="dpe-teacher-related">@foreach($related as $item)<li><a href="{{ route('teacher.schedule.show', $item) }}">{{ $item->id === $session->rescheduled_from_session_id ? 'Jadwal asal' : 'Jadwal pengganti' }} · {{ $item->planned_start_at->format('d M Y H:i') }} · {{ $item->program?->name }}</a></li>@endforeach</ul>@endif</div>
        @endif
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Daftar peserta</h2><p>Peserta memakai daftar yang sama dengan Absensi Siswa.</p></div><span class="dpe-dashboard-badge">{{ $participants->count() }} siswa</span></header>
            @if($participants->isEmpty())<x-dashboard.empty title="Peserta belum terdaftar" message="Hubungi Admin untuk menambahkan peserta sesi private atau anggota kelas dengan pendaftaran yang berlaku." icon="student" />
            @else
                <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-teacher-table"><thead><tr><th>Nama siswa</th><th>Sekolah</th><th>Kelas sekolah</th><th>Kehadiran</th><th>Aksi</th></tr></thead><tbody>
                    @foreach($participants as $item)<tr><td><strong>{{ $item['student']->full_name }}</strong></td><td>{{ $item['student']->school_name ?: '—' }}</td><td>{{ $item['student']->grade_name ?: '—' }}</td><td><span class="dpe-dashboard-badge">{{ $item['cell']['label'] }}</span></td><td>@if($item['canViewProfile'])<a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('teacher.students.show', ['student'=>$item['student'],'month'=>$month->format('Y-m')]) }}">Profil siswa</a>@else<span>—</span>@endif</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </article>
    </div>
@endsection
