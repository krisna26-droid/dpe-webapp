@extends('layouts.dashboard')
@section('title', 'Monitoring Laporan')
@section('heading', 'Monitoring Laporan')
@section('description', 'Pantau laporan bulanan, video, pembayaran, serta kesiapan PDF tiga bulan.')
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Laporan / Monitoring</p><h2>{{ $monthLabel }}</h2><p>Ringkasan dan daftar menggunakan filter yang sama. Monitoring laporan memuat periode yang sudah disiapkan Admin.</p></div>
        <div class="dpe-operations-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($actor->role_code.'.dashboard') }}"><x-dashboard.icon name="back" />Kembali</a><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($actor->role_code.'.student-progress.index', ['month' => $filters['month']]) }}">Perkembangan &amp; Pesan</a></div>
    </section>
    <article class="dpe-dashboard-card dpe-operations-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Filter monitoring</h2><p>Rapor tiga bulan ditampilkan jika siklusnya mencakup bulan yang dipilih.</p></div></header>
        <div class="dpe-dashboard-card__body"><form method="GET" action="{{ route('monitoring.index') }}" class="dpe-operations-filter">
            <div class="dpe-dashboard-field"><label for="monitor-month">Bulan</label><input id="monitor-month" type="month" name="month" value="{{ $filters['month'] }}" required min="2000-01" max="2100-12"></div>
            <div class="dpe-dashboard-field"><label for="monitor-branch">Cabang</label><select id="monitor-branch" name="branch_id"><option value="">Semua cabang dalam cakupan</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div class="dpe-dashboard-field"><label for="monitor-teacher">Teacher</label><select id="monitor-teacher" name="teacher_id"><option value="">Semua Teacher dalam cakupan</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === $teacher->id)>{{ $teacher->user?->full_name ?: 'Teacher' }}</option>@endforeach</select></div>
            <div class="dpe-dashboard-field"><label for="monitor-name">Nama siswa</label><input id="monitor-name" type="search" name="q" maxlength="100" value="{{ $filters['q'] }}" placeholder="Cari nama siswa"></div>
            <div class="dpe-dashboard-field"><label for="monitor-status">Status laporan bulanan</label><select id="monitor-status" name="status"><option value="all">Semua status</option>@foreach ($statusLabels as $code => $label)<option value="{{ $code }}" @selected($filters['status'] === $code)>{{ $label }}</option>@endforeach</select></div>
            <div class="dpe-dashboard-field"><label for="monitor-final-status">Status rapor tiga bulan</label><select id="monitor-final-status" name="final_status"><option value="all">Semua status</option>@foreach ($statusLabels as $code => $label)<option value="{{ $code }}" @selected($filters['final_status'] === $code)>{{ $label }}</option>@endforeach</select></div>
            <div class="dpe-operations-actions"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('monitoring.index') }}">Reset</a></div>
        </form></div>
    </article>
    @include('operations._summary')
    <div class="dpe-operations-actions dpe-operations-spaced"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('exports.index', ['dataset' => 'monthly-reports', 'from' => $filters['month'].'-01', 'to' => \Carbon\CarbonImmutable::parse($filters['month'].'-01')->endOfMonth()->toDateString(), 'branch_id' => $filters['branch_id'], 'teacher_id' => $filters['teacher_id'], 'q' => $filters['q'], 'status' => $filters['status']]) }}">Rekap dan ekspor laporan</a></div>
    <article class="dpe-dashboard-card dpe-operations-spaced" id="rows">
        <header class="dpe-dashboard-card__head"><div><h2>Laporan bulanan</h2><p>{{ $summary['video_missing'] }} perlu pemeriksaan video · {{ $summary['unpaid'] }} pembayaran belum tervalidasi. Status pembayaran diperiksa untuk siswa dan bulan kalender yang sama.</p></div><span class="dpe-dashboard-badge">{{ $rows->total() }} laporan</span></header>
        @if ($rows->isEmpty())<x-dashboard.empty title="Belum ada laporan sesuai filter" message="Periksa bulan/cabang/Teacher atau buka Perkembangan & Pesan untuk menyiapkan periode yang diperlukan." icon="report" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-operations-table"><thead><tr><th>Siswa / Bulan</th><th>Cabang / Teacher</th><th>Persetujuan</th><th>Tenggat</th><th>Video</th><th>Pembayaran</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($rows as $row)@php($report = $row['report'])
                    <tr><td><strong>{{ $report->student?->full_name ?: 'Siswa' }}</strong><small>{{ $monthLabel }}</small></td><td><strong>{{ $report->branch?->name ?: '—' }}</strong><small>{{ $report->teacher?->user?->full_name ?: 'Teacher perlu diperiksa' }}</small></td>
                        <td><x-dashboard.status :status="$report->status" :label="$statusLabels[$report->status] ?? 'Perlu diperiksa'" /></td>
                        <td>{{ $report->due_on?->format('d/m/Y') ?: 'Belum ditetapkan' }}@if ($row['deadline'] === 'overdue')<small class="dpe-operations-warning">Terlewati</small>@elseif ($row['deadline'] === 'near')<small>Mendekat</small>@elseif ($report->status === 'approved')<small>Sudah disetujui</small>@endif</td>
                        <td>{{ $row['video']['label'] }}@if ($report->status === 'approved')<small>Salinan persetujuan</small>@endif</td><td><span class="dpe-dashboard-badge {{ $row['paid'] ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $row['paid'] ? 'Tervalidasi' : 'Belum tervalidasi' }}</span></td>
                        <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ $row['path'] }}">Periksa laporan</a></td>
                    </tr>
                @endforeach
            </tbody></table></div><x-dashboard.pagination :paginator="$rows" />
        @endif
    </article>
    <article class="dpe-dashboard-card dpe-operations-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Ringkasan per Teacher</h2><p>Jumlah laporan dan kekurangan berikut berasal dari daftar bulanan dengan filter yang sama.</p></div></header>
        @if ($teacherSummary->isEmpty())<x-dashboard.empty title="Belum ada tanggung jawab laporan sesuai filter" icon="teacher" />
        @else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-operations-table"><thead><tr><th>Teacher</th><th>Laporan</th><th>Approved</th><th>Pending</th><th>Tenggat terlewati</th><th>Periksa video</th><th>Belum bayar tervalidasi</th></tr></thead><tbody>
            @foreach ($teacherSummary as $row)<tr><td><strong>{{ $row['teacher']?->user?->full_name ?: 'Teacher perlu diperiksa' }}</strong></td>@foreach (['total', 'approved', 'pending', 'overdue', 'video_missing', 'unpaid'] as $key)<td>{{ $row['summary'][$key] }}</td>@endforeach</tr>@endforeach
        </tbody></table></div>@endif
    </article>
    <article class="dpe-dashboard-card" id="finalRows">
        <header class="dpe-dashboard-card__head"><div><h2>Rapor tiga bulan dan kesiapan PDF</h2><p>Persetujuan final, tiga laporan bulanan, tiga pembayaran, dan file PDF diperiksa terpisah.</p></div><span class="dpe-dashboard-badge">{{ $finalRows->total() }} rapor</span></header>
        @if ($finalRows->isEmpty())<x-dashboard.empty title="Belum ada rapor tiga bulan sesuai filter" message="Siapkan rapor melalui menu Rapor Tiga Bulan setelah siklus siswa tersedia." icon="report" />
        @else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-operations-table"><thead><tr><th>Siswa / Siklus</th><th>Cabang / Teacher</th><th>Persetujuan final</th><th>Tiga bulan</th><th>PDF untuk portal</th><th>Aksi</th></tr></thead><tbody>
            @foreach ($finalRows as $row)@php($report = $row['report'])
                <tr><td><strong>{{ $report->student?->full_name ?: 'Siswa' }}</strong><small>{{ $report->cycle?->start_month?->format('m/Y') }}–{{ $report->cycle?->end_month?->format('m/Y') }}</small></td><td><strong>{{ $report->branch?->name ?: '—' }}</strong><small>{{ $report->teacher?->user?->full_name ?: 'Teacher perlu diperiksa' }}</small></td>
                    <td><x-dashboard.status :status="$report->status" :label="$statusLabels[$report->status] ?? 'Perlu diperiksa'" /><small>Tenggat: {{ $report->cycle?->share_due_on?->format('d/m/Y') ?: 'Belum ditetapkan' }}</small>@if ($row['deadline'] === 'overdue')<small class="dpe-operations-warning">Terlewati</small>@elseif ($row['deadline'] === 'near')<small>Mendekat</small>@endif</td>
                    <td>@foreach ($row['portal']['months'] as $month)<small>{{ $month['label'] }} · laporan {{ $month['monthly_ok'] ? 'Approved' : 'belum Approved' }} · bayar {{ $month['paid'] ? 'tervalidasi' : 'belum tervalidasi' }}</small>@endforeach</td>
                    <td><span class="dpe-dashboard-badge {{ $row['portal']['ready'] ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $row['portal']['ready'] ? 'Tersedia' : 'Belum siap' }}</span>@if (! $row['portal']['ready'])<ul class="dpe-operations-reasons">@foreach ($row['portal']['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>@endif</td>
                    <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ $row['path'] }}">Periksa rapor</a></td>
                </tr>
            @endforeach
        </tbody></table></div><x-dashboard.pagination :paginator="$finalRows" />@endif
    </article>
@endsection
