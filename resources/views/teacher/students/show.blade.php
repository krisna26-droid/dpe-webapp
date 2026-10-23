@php
    $student = $row['student'];
@endphp
@extends('layouts.dashboard')
@section('title', 'Detail Siswa Binaan')
@section('heading', 'Detail Siswa Binaan')
@section('description', 'Profil belajar dan pertemuan siswa yang menjadi tanggung jawab Anda.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Teacher / Siswa Binaan</p><h2>{{ $student->full_name }}</h2><p>{{ $month->locale('id')->translatedFormat('F Y') }} · {{ ['active'=>'Siswa aktif','inactive'=>'Siswa nonaktif'][$student->status] ?? $student->status }}</p></div>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.students.index', ['month'=>$month->format('Y-m')]) }}"><x-dashboard.icon name="back" />Kembali ke Siswa Binaan</a>
    </section>
    <div class="dpe-teacher-stack">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Profil belajar</h2><p>Identitas dasar dan pendaftaran pada bulan pilihan.</p></div></header>
            <div class="dpe-dashboard-card__body"><div class="dpe-dashboard-detail-grid">
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Nama siswa</p><p class="dpe-dashboard-detail__value">{{ $student->full_name }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Mulai les</p><p class="dpe-dashboard-detail__value">{{ $student->began_on->format('d M Y') }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Sekolah</p><p class="dpe-dashboard-detail__value">{{ $student->school_name ?: 'Belum diisi' }}</p></div>
                <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Kelas sekolah</p><p class="dpe-dashboard-detail__value">{{ $student->grade_name ?: 'Belum diisi' }}</p></div>
                <div class="dpe-dashboard-detail dpe-dashboard-detail--full"><p class="dpe-dashboard-detail__label">Program / Cabang</p>@forelse($student->enrollments as $enrollment)<p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $enrollment->program?->name }} · {{ $enrollment->branch?->name }}</p>@empty<p class="dpe-dashboard-detail__value">Pendaftaran belum tersedia pada bulan ini.</p>@endforelse</div>
                <div class="dpe-dashboard-detail dpe-dashboard-detail--full"><p class="dpe-dashboard-detail__label">Kelas bersama Anda</p>@forelse($groups as $membership)<p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $membership->classGroup->name }} · {{ $membership->classGroup->branch?->name }}</p>@empty<p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">Tidak ada kelompok kelas aktif bersama Anda pada bulan ini. Siswa dapat ditugaskan langsung atau mengikuti sesi private/pengganti.</p>@endforelse</div>
            </div></div>
        </article>
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Perkembangan & rapor bulanan</h2><p>{{ $month->locale('id')->translatedFormat('F Y') }}</p></div></header>
            <div class="dpe-dashboard-card__body">
                @if($row['report'])
                    <div class="dpe-teacher-notice"><p>Periode rapor sudah tersedia. Lengkapi perkembangan dan video melalui menu Perkembangan & Pesan.</p><p>Status: <strong>{{ ['draft'=>'Draf','revision'=>'Perlu revisi','submitted'=>'Diajukan','approved'=>'Disetujui'][$row['report']->status] ?? $row['report']->status }}</strong></p></div>
                    <div class="dpe-teacher-form-actions"><a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('teacher.student-progress.edit', $row['report']) }}">Buka perkembangan</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.monthly-reports.show', $row['report']) }}">Lihat laporan bulanan</a></div>
                @else
                    <div class="dpe-teacher-notice"><p>{{ $row['otherOwner'] ? 'Periode ini dikelola Teacher lain. Hubungi Admin jika penetapan pemilik laporan perlu diperiksa.' : 'Admin belum menyiapkan periode rapor bulan ini. Anda tetap dapat melihat siswa dan membuka absensi dari pertemuan yang ditugaskan.' }}</p></div>
                @endif
            </div>
        </article>
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Pertemuan bersama Anda</h2><p>Daftar mengikuti peserta dan tanggal pelaksanaan pertemuan.</p></div><span class="dpe-dashboard-badge">{{ $sessions->total() }} pertemuan</span></header>
            @if($sessions->isEmpty())<x-dashboard.empty title="Belum ada pertemuan pada bulan ini" message="Siswa sudah tercantum dalam penugasan Anda. Jadwal dan peserta pertemuan disiapkan oleh Admin." icon="calendar" />
            @else
                <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-teacher-table"><thead><tr><th>Tanggal / waktu</th><th>Kelas / Program</th><th>Cabang / Lokasi</th><th>Status</th><th>Aksi</th></tr></thead><tbody>@foreach($sessions as $sessionRow)<tr><td><strong>{{ $sessionRow['meetingAt']->format('d M Y') }}</strong><small>{{ $sessionRow['meetingAt']->format('H:i') }}–{{ $sessionRow['endsAt']->format('H:i') }}</small></td><td><strong>{{ $sessionRow['session']->classGroup?->name ?: 'Private / tanpa kelompok' }}</strong><small>{{ $sessionRow['session']->program?->name }}</small></td><td><strong>{{ $sessionRow['session']->branch?->name }}</strong><small>{{ $sessionRow['session']->isAtStudentHome() ? 'Rumah siswa' : 'Di cabang' }}</small></td><td><span class="dpe-dashboard-badge">{{ $sessionRow['status'] }}</span></td><td><div class="dpe-operations-actions"><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('teacher.schedule.show', $sessionRow['session']) }}">Detail kelas</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.student-attendance.show', $sessionRow['session']) }}">Absensi Siswa</a></div></td></tr>@endforeach</tbody></table></div><x-dashboard.pagination :paginator="$sessions" />
            @endif
        </article>
    </div>
@endsection
