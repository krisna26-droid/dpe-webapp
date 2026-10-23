@extends('layouts.dashboard')

@section('title', 'Data Guru')
@section('heading', 'Data Guru')
@section('description', 'Kelola profil guru yang ditugaskan pada cabang Anda.')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/teaching-directory.css') }}?v={{ filemtime(public_path('assets/dpe/teaching-directory.css')) }}">
@endpush

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional Cabang / Guru</p>
            <h2>Data guru</h2>
            <p>Lihat pengajar, siswa yang diajar, dan penugasan pada cabang yang Anda kelola.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('admin.teachers.create') }}">
                <x-dashboard.icon name="plus" />
                <span>Tambah guru</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar guru cabang</h2>
                <p>{{ $teachers->total() }} guru terhubung ke cabang aktif Anda</p>
            </div>
            <form class="dpe-dashboard-inline-search" method="GET" action="{{ route('admin.teachers.index') }}">
                <label class="sr-only" for="teacher-search">Cari guru</label>
                <input id="teacher-search" name="search" type="search" value="{{ request('search') }}" placeholder="Cari nama atau WhatsApp">
                <button class="dpe-dashboard-button dpe-dashboard-button--soft" type="submit"><x-dashboard.icon name="search" /> Cari</button>
            </form>
        </header>

        @if ($teachers->count() === 0)
            <x-dashboard.empty
                title="Belum ada guru di cabang ini"
                message="Guru akan tampil setelah memiliki penugasan cabang atau hubungan mengajar aktif pada cabang yang Anda kelola."
                icon="teacher"
                :action-url="route('admin.teachers.create')"
                action-label="Tambah profil guru"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Guru</th>
                            <th>WhatsApp</th>
                            <th>Cabang aktif</th>
                            <th>Siswa yang diajar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teachers as $teacher)
                            @php
                                $today = now()->startOfDay();
                                $activeAssignments = $teacher->branchAssignments->filter(fn ($assignment) =>
                                    $assignment->starts_on?->lte($today)
                                    && ($assignment->ends_on === null || $assignment->ends_on->gte($today))
                                );
                                $teacherName = $teacher->user?->full_name ?: ($teacher->user?->username ?: 'Guru tidak ditemukan');
                                $summary = $teacherSummaries->get($teacher->id, ['count' => 0, 'names' => collect()]);
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.teachers.show', $teacher) }}"><strong>{{ $teacherName }}</strong></a>
                                    @if ($teacher->user?->email)<br><span>{{ $teacher->user->email }}</span>@endif
                                </td>
                                <td>{{ $teacher->whatsapp_number ?: '—' }}</td>
                                <td>
                                    @if ($activeAssignments->isNotEmpty())
                                        {{ $activeAssignments->map(fn ($assignment) => $assignment->branch?->name ?: 'Cabang')->join(', ') }}
                                    @else
                                        <span>Belum ada cabang aktif</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="dpe-teaching-people">
                                        <strong>{{ $summary['count'] }} siswa</strong>
                                        @if ($summary['count'] > 0)
                                            <ul class="dpe-teaching-names">
                                                @foreach ($summary['names'] as $studentName)<li>{{ $studentName }}</li>@endforeach
                                            </ul>
                                            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.teachers.show', $teacher) }}#teacher-students" aria-label="Lihat semua siswa yang diajar oleh {{ $teacherName }}">Lihat semua</a>
                                        @else
                                            <span>Belum ada siswa aktif</span>
                                        @endif
                                    </div>
                                </td>
                                <td><x-dashboard.status :status="$teacher->is_active" :label="$teacher->is_active ? 'Aktif' : 'Nonaktif'" /></td>
                                <td>
                                    <div class="dpe-dashboard-table-actions">
                                        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('admin.teachers.show', $teacher) }}">Detail</a>
                                        @if ($editableTeacherIds->contains($teacher->id))
                                            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('admin.teachers.edit', $teacher) }}">Edit</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$teachers" />
        @endif
    </article>
@endsection
