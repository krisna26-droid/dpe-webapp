@extends('layouts.dashboard')
@section('title', 'Siapkan Periode Siswa')
@section('heading', 'Siapkan Periode Siswa')
@section('description', 'Tetapkan Teacher dan target video untuk satu siswa-bulan.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/student-progress.css') }}?v={{ filemtime(public_path('assets/dpe/student-progress.css')) }}">
@endpush
@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Perkembangan & Pesan / Persiapan</p><h2>{{ $student->full_name }}</h2><p>{{ $month->locale('id')->translatedFormat('F Y') }} · {{ $enrollment->branch?->name }}</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index', ['month' => $month->format('Y-m')]) }}"><x-dashboard.icon name="back" />Kembali ke daftar siswa</a></section>
    <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Periode pembelajaran</h2><p>Periode yang sudah tersedia dipertahankan ketika persiapan diulang.</p></div></header><div class="dpe-dashboard-card__body">
        <div class="dpe-progress-meta"><span><strong>Mulai les</strong>{{ $student->began_on->format('d M Y') }}</span><span><strong>Pencatatan periode dimulai</strong>{{ \Carbon\Carbon::parse($startsOn)->format('d M Y') }}</span><span><strong>Bulan</strong>{{ $month->locale('id')->translatedFormat('F Y') }}</span></div>
        @if ($firstPartialMonth)<div class="dpe-progress-notice" role="status"><x-dashboard.icon name="info" /><p>Siswa mulai di tengah bulan. Tetapkan target video berdasarkan pertemuan yang dijadwalkan bulan ini.</p></div>@endif
        @if ($unavailableAssignedTeachers->isNotEmpty())
            <div class="dpe-progress-notice" role="status"><x-dashboard.icon name="info" /><p>Teacher yang ditugaskan belum dapat dipilih: <strong>{{ $unavailableAssignedTeachers->pluck('user.full_name')->filter()->join(', ') }}</strong>. Periksa status profil dan akun Teacher; keduanya harus aktif dan akun memakai peran Teacher.</p></div>
        @endif
        @if ($teachers->isEmpty())
            <div class="dpe-progress-notice" role="status"><x-dashboard.icon name="info" /><p><strong>Belum ada Teacher yang dapat dipilih.</strong> Periksa Teacher siswa atau penugasan Teacher cabang selama bulan pilihan. <a href="{{ str_starts_with($routeBase, 'superadmin.') ? route('superadmin.teacher-branch-assignments.index') : route('admin.teachers.index') }}">{{ str_starts_with($routeBase, 'superadmin.') ? 'Buka Guru dan Penugasan' : 'Buka Data Guru' }}</a> untuk memeriksa profil dan penugasannya. Periode dapat disimpan setelah Teacher yang sesuai tersedia.</p></div>
        @endif
        <form method="POST" action="{{ route($routeBase.'.prepare', $student) }}" class="dpe-dashboard-form">@csrf<input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
            <div class="dpe-dashboard-form-grid">
                <div class="dpe-dashboard-field">
                    <label for="progress-teacher">Teacher pemilik laporan</label>
                    <select id="progress-teacher" name="teacher_id" required @disabled($teachers->isEmpty())>
                        <option value="">{{ $teachers->isEmpty() ? 'Belum ada Teacher yang sesuai' : 'Pilih Teacher' }}</option>
                        @if ($assignedTeachers->isNotEmpty())
                            <optgroup label="Teacher siswa ini">
                                @foreach ($assignedTeachers as $teacher)
                                    <option value="{{ $teacher->id }}" @selected(old('teacher_id', $suggestedTeacher) === $teacher->id)>{{ $teacher->user?->full_name }} · {{ implode(', ', $teacherSources->get($teacher->id, [])) }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                        @if ($teachers->count() > $assignedTeachers->count())
                            <optgroup label="{{ $assignedTeachers->isEmpty() ? 'Teacher cabang' : 'Teacher cabang lainnya' }}">
                                @foreach ($teachers->whereNotIn('id', $assignedTeachers->pluck('id')) as $teacher)
                                    <option value="{{ $teacher->id }}" @selected(old('teacher_id', $suggestedTeacher) === $teacher->id)>{{ $teacher->user?->full_name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    </select>
                    <p class="dpe-progress-help">Pilihan mengikuti penugasan siswa, kelas/pertemuan, atau cabang selama {{ $month->locale('id')->translatedFormat('F Y') }} dan masa pendaftaran siswa.</p>
                    @if ($assignedTeachers->isEmpty())
                        <p class="dpe-progress-help">Belum ada Teacher siswa yang aktif pada periode ini. Admin dapat memilih Teacher cabang untuk menjadi pemilik laporan.</p>
                    @elseif ($suggestedTeacher)
                        <p class="dpe-progress-help">Saran mengikuti penugasan yang sudah ada. Periksa pilihan sebelum menyimpan; penugasan mengajar tetap mengikuti pengaturannya sendiri.</p>
                    @else
                        <p class="dpe-progress-help">Lebih dari satu Teacher mengajar siswa ini. Pilih satu Teacher pemilik laporan bulan ini.</p>
                    @endif
                    @error('teacher_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field"><label for="progress-target">Target video bulan ini{{ $firstPartialMonth ? ' (wajib ditetapkan)' : '' }}</label><input id="progress-target" type="number" name="video_target" min="0" max="32767" value="{{ old('video_target', $firstPartialMonth ? '' : $defaultTarget) }}" @required($firstPartialMonth)><p class="dpe-progress-help">{{ $firstPartialMonth ? 'Masukkan jumlah sesuai jadwal. Nilai 0 diperbolehkan jika tidak ada target video.' : 'Target awal mengikuti pengaturan sistem dan disimpan untuk bulan ini.' }}</p>@error('video_target')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
            </div>
            <div class="dpe-dashboard-form-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index', ['month' => $month->format('Y-m')]) }}">Batal</a><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary" @disabled($teachers->isEmpty())>Siapkan periode</button></div>
        </form>
    </div></article>
@endsection
