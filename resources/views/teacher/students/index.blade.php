@extends('layouts.dashboard')
@section('title', 'Siswa Binaan')
@section('heading', 'Siswa Binaan')
@section('description', 'Siswa dari penugasan, kelas, sesi, atau laporan yang menjadi tanggung jawab Anda.')

@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Teacher / Kelas Saya</p><h2>Kenali siswa yang Anda bimbing.</h2><p>Siswa tetap tampil sebelum periode rapor disiapkan oleh Admin.</p></div></section>
    @include('teacher.workspace._tabs')
    <div class="dpe-teacher-stack">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Filter siswa</h2><p>Daftar mengikuti penugasan yang berlaku pada bulan pilihan.</p></div></header>
            <div class="dpe-dashboard-card__body"><form method="GET" action="{{ route('teacher.students.index') }}" class="dpe-teacher-filters">
                <div class="dpe-dashboard-field"><label for="students-month">Bulan penugasan</label><input id="students-month" name="month" type="month" min="2000-01" max="2100-12" value="{{ $filters['month'] }}"></div>
                <div class="dpe-dashboard-field"><label for="students-q">Nama siswa</label><input id="students-q" name="q" type="search" maxlength="120" placeholder="Cari nama siswa" value="{{ $filters['q'] ?? '' }}"></div>
                <div class="dpe-dashboard-field"><label for="students-status">Status siswa</label><select id="students-status" name="status"><option value="">Semua status</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option></select></div>
                <div class="dpe-teacher-filter-actions"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.students.index') }}">Reset</a></div>
            </form></div>
        </article>
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>{{ $month->locale('id')->translatedFormat('F Y') }}</h2><p>Buka profil untuk melihat kelas dan pertemuan siswa bersama Anda.</p></div><span class="dpe-dashboard-badge">{{ $students->total() }} siswa</span></header>
            @if($rows->isEmpty())<x-dashboard.empty title="Belum ada siswa untuk filter ini" message="Periksa bulan dan pencarian. Admin dapat menugaskan siswa langsung, menambah anggota kelas, atau menetapkan peserta sesi Anda." icon="student" />
            @else
                <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-teacher-table"><thead><tr><th>Nama siswa</th><th>Sekolah</th><th>Kelas sekolah</th><th>Program / Cabang</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                    @foreach($rows as $row)
                        <tr><td><strong>{{ $row['student']->full_name }}</strong><small>Mulai les {{ $row['student']->began_on->format('d M Y') }}</small></td><td>{{ $row['student']->school_name ?: '—' }}</td><td>{{ $row['student']->grade_name ?: '—' }}</td>
                            <td>@forelse($row['student']->enrollments as $enrollment)<strong>{{ $enrollment->program?->name ?: 'Program belum tersedia' }}</strong><small>{{ $enrollment->branch?->name }}</small>@empty<span>Pendaftaran belum tersedia</span>@endforelse</td>
                            <td><span class="dpe-dashboard-badge {{ $row['student']->status === 'active' ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ ['active'=>'Aktif','inactive'=>'Nonaktif'][$row['student']->status] ?? $row['student']->status }}</span></td>
                            <td><div class="dpe-operations-actions"><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('teacher.students.show', ['student'=>$row['student'],'month'=>$filters['month']]) }}">Lihat siswa</a>@if($row['report'])<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.student-progress.edit', $row['report']) }}">Perkembangan</a>@endif</div><small>{{ $row['report'] ? 'Periode rapor sudah tersedia' : ($row['otherOwner'] ? 'Periode dikelola Teacher lain' : 'Menunggu Admin menyiapkan periode rapor') }}</small></td>
                        </tr>
                    @endforeach
                </tbody></table></div><x-dashboard.pagination :paginator="$students" />
            @endif
        </article>
    </div>
@endsection
