@extends('layouts.dashboard')
@section('title', 'Penugasan Teacher ke Siswa')
@section('heading', 'Siswa & Pendaftaran')
@section('description', 'Periksa Teacher yang ditugaskan langsung kepada siswa dan periode tugasnya.')
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Operasional / Penugasan siswa</p><h2>Teacher siswa</h2><p>Penugasan langsung melengkapi Teacher kelas dan Teacher pertemuan. Pemilik laporan bulanan ditetapkan ketika Admin menyiapkan periode.</p></div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.student-teacher-assignments.create') }}"><x-dashboard.icon name="plus" /> Tambah penugasan</a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.students.index') }}"><x-dashboard.icon name="back" /> Kembali ke Siswa & Pendaftaran</a>
        </div>
    </section>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Daftar penugasan siswa</h2><p>{{ $assignments->total() }} penugasan tercatat</p></div></header>
        @if ($assignments->isEmpty())
            <x-dashboard.empty title="Belum ada penugasan langsung" message="Teacher kelas dan Teacher pertemuan tetap mengikuti kelas dan sesi yang ditugaskan." icon="teacher" :action-url="route('superadmin.student-teacher-assignments.create')" action-label="Tambah penugasan" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table">
                <thead><tr><th>Siswa</th><th>Teacher</th><th>Mulai</th><th>Berakhir</th><th>Prioritas laporan</th><th>Aksi</th></tr></thead>
                <tbody>
                    @foreach ($assignments as $assignment)
                        <tr>
                            <td><strong>{{ $assignment->student?->full_name ?: '—' }}</strong></td>
                            <td>{{ $assignment->teacher?->user?->full_name ?: '—' }}</td>
                            <td>{{ $assignment->starts_on?->format('d M Y') ?: '—' }}</td>
                            <td>{{ $assignment->ends_on?->format('d M Y') ?: 'Berjalan' }}</td>
                            <td>{{ $assignment->is_report_owner ? 'Pemilik laporan' : 'Teacher pengajar' }}</td>
                            <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-teacher-assignments.show', $assignment) }}">Detail</a> <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.student-teacher-assignments.edit', $assignment) }}">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
            <x-dashboard.pagination :paginator="$assignments" />
        @endif
    </article>
@endsection
