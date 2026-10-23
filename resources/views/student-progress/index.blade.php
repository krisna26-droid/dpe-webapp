@extends('layouts.dashboard')
@section('title', 'Perkembangan & Pesan')
@section('heading', 'Perkembangan & Pesan')
@section('description', 'Progres bulanan, video belajar, dan draf pesan untuk orang tua.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/student-progress.css') }}?v={{ filemtime(public_path('assets/dpe/student-progress.css')) }}">
@endpush
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Pembelajaran / Progres bulanan</p><h2>Ikuti perkembangan setiap siswa.</h2><p>{{ $canManage ? 'Siapkan periode, tetapkan Teacher pemilik laporan, lalu pantau isian pembelajaran.' : 'Daftar mengikuti penugasan mengajar Anda. Admin menyiapkan periode dan menetapkan Teacher pemilik laporan.' }}</p></div>
        <div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($monthlyReportBase.'.index', ['month' => $filters['month'], 'status' => 'all']) }}"><x-dashboard.icon name="report" />Laporan Bulanan</a>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route(str_replace('student-progress', 'learning-notes', $routeBase).'.index') }}"><x-dashboard.icon name="book" />Catatan Belajar</a>
        </div>
    </section>
    <article class="dpe-dashboard-card dpe-progress-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Filter siswa</h2><p>Pilih bulan kalender sejak siswa mulai les.</p></div></header>
        <div class="dpe-dashboard-card__body">
            <form method="GET" action="{{ route($routeBase.'.index') }}" class="dpe-dashboard-form">
                <div class="dpe-dashboard-form-grid dpe-dashboard-form-grid--three">
                    <div class="dpe-dashboard-field"><label for="progress-month">Bulan</label><input id="progress-month" name="month" type="month" value="{{ $filters['month'] }}">@error('month')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                    <div class="dpe-dashboard-field"><label for="progress-branch">Cabang</label><select id="progress-branch" name="branch_id"><option value="">Semua cabang saya</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="dpe-dashboard-field"><label for="progress-search">Nama siswa</label><input id="progress-search" type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama siswa"></div>
                </div>
                <div class="dpe-progress-filter-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index') }}">Reset</a></div>
            </form>
        </div>
    </article>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>{{ $month->locale('id')->translatedFormat('F Y') }}</h2><p>Delapan kategori progres dan target video ditampilkan untuk siswa-bulan yang dipilih.</p></div><span class="dpe-dashboard-badge">{{ $students->total() }} siswa</span></header>
        @if ($rows->isEmpty())
            <x-dashboard.empty title="Belum ada siswa untuk filter ini" :message="$canManage ? 'Periksa bulan, pendaftaran cabang siswa, atau filter pencarian.' : 'Periksa bulan dan filter. Penugasan siswa, anggota kelas Anda, atau peserta sesi kelas/private harus berlaku pada bulan yang dipilih.'" icon="student" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-progress-list-table">
                <thead><tr><th>Siswa</th><th>Cabang / Teacher</th><th>Progres</th><th>Video</th><th>Status</th><th>Aksi</th></tr></thead>
                <tbody>@foreach ($rows as $row)
                    <tr>
                        <td><strong>{{ $row['student']->full_name }}</strong><small>Mulai les {{ $row['student']->began_on->format('d M Y') }}</small></td>
                        <td>{{ $row['report']?->branch?->name ?: $row['student']->enrollments->pluck('branch.name')->filter()->unique()->join(', ') ?: 'Periksa pendaftaran' }}<small>{{ $row['report']?->teacher?->user?->full_name ?: ($row['otherReportOwner'] ? 'Pemilik laporan: Teacher lain' : ($canManage ? 'Teacher laporan belum ditetapkan' : 'Anda ditugaskan mengajar')) }}</small></td>
                        <td>@if ($row['otherReportOwner'])<span>—</span>@else<span class="dpe-dashboard-badge {{ $row['completed'] === 8 ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $row['completed'] }} / 8 kategori</span>@endif</td>
                        <td>@if ($row['report'])<strong>{{ $row['videoCount'] }}{{ $row['targetReady'] ? ' / '.$row['report']->video_target : ' video' }}</strong><small>{{ $row['targetReady'] ? 'Target tersimpan untuk periode ini' : 'Menunggu target bulan pertama dari Admin' }}</small>@elseif ($row['otherReportOwner'])<span>—</span>@else<span>Belum disiapkan</span>@endif</td>
                        <td><x-dashboard.status :status="$row['otherReportOwner'] ? 'other_owner' : ($row['report']?->status ?? 'unprepared')" :label="$row['otherReportOwner'] ? 'Dikelola Teacher lain' : (['draft' => 'Draf', 'revision' => 'Perlu revisi', 'submitted' => 'Diajukan', 'approved' => 'Disetujui'][$row['report']?->status] ?? ($row['report']?->status ?: 'Belum ada periode'))" /></td>
                        <td>@if ($row['report'])<a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($routeBase.'.edit', $row['report']) }}"><x-dashboard.icon name="report" />Buka perkembangan</a>@elseif ($canManage)<a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($routeBase.'.preparation', ['student' => $row['student'], 'month' => $filters['month']]) }}">Siapkan periode</a>@elseif ($row['otherReportOwner'])<small>Hubungi Admin untuk penetapan pemilik laporan</small>@else<small>Menunggu Admin menyiapkan periode</small>@endif</td>
                    </tr>
                @endforeach</tbody>
            </table></div><x-dashboard.pagination :paginator="$students" />
        @endif
    </article>
@endsection
