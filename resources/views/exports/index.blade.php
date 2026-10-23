@extends('layouts.dashboard')
@section('title', 'Data & Ekspor')
@section('heading', 'Data & Ekspor')
@section('description', 'Cari data operasional dalam cakupan Anda dan unduh hasil filter sebagai Excel atau PDF.')
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Operasional / Rekap</p><h2>{{ $title }}</h2><p>{{ $description }}</p></div>
        <div class="dpe-operations-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($actor->role_code.'.dashboard') }}"><x-dashboard.icon name="back" />Kembali</a><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('monitoring.index') }}">Monitoring Laporan</a></div>
    </section>
    <nav class="dpe-export-tabs" aria-label="Jenis rekap">
        @foreach (\App\Services\OperationalExportDefinition::DATASETS as $key => $label)
            <a class="dpe-export-tab {{ $filters['dataset'] === $key ? 'is-active' : '' }}" href="{{ route('exports.index', ['dataset' => $key, 'from' => $filters['from'], 'to' => $filters['to']]) }}" @if ($filters['dataset'] === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <article class="dpe-dashboard-card dpe-operations-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Filter rekap</h2><p>Pilih jenis rekap, atur filter, lalu klik Tampilkan. Rentang maksimal 366 hari.</p></div></header>
        <div class="dpe-dashboard-card__body"><form method="GET" action="{{ route('exports.index') }}" class="dpe-export-filter">
            <input type="hidden" name="dataset" value="{{ $filters['dataset'] }}">
            <div class="dpe-dashboard-field"><label for="export-from">Dari tanggal</label><input type="date" id="export-from" name="from" min="2000-01-01" max="2100-12-31" value="{{ $filters['from'] }}" required></div>
            <div class="dpe-dashboard-field"><label for="export-to">Sampai tanggal</label><input type="date" id="export-to" name="to" min="2000-01-01" max="2100-12-31" value="{{ $filters['to'] }}" required></div>
            <div class="dpe-dashboard-field"><label for="export-branch">Cabang</label><select id="export-branch" name="branch_id"><option value="">Semua dalam cakupan</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div class="dpe-dashboard-field"><label for="export-teacher">Teacher</label><select id="export-teacher" name="teacher_id"><option value="">Semua dalam cakupan</option>@foreach ($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === $teacher->id)>{{ $teacher->user?->full_name ?: 'Teacher perlu diperiksa' }}</option>@endforeach</select></div>
            @if (in_array('program_id', $supportedFilters, true))<div class="dpe-dashboard-field"><label for="export-program">Program</label><select id="export-program" name="program_id"><option value="">Semua program</option>@foreach ($programs as $program)<option value="{{ $program->id }}" @selected($filters['program_id'] === $program->id)>{{ $program->name }}</option>@endforeach</select></div>@endif
            @if (in_array('class_type', $supportedFilters, true))<div class="dpe-dashboard-field"><label for="export-class-type">Jenis kelas</label><select id="export-class-type" name="class_type"><option value="">Semua jenis</option>@foreach ($classTypes as $type)<option value="{{ $type }}" @selected($filters['class_type'] === $type)>{{ $type }}</option>@endforeach</select></div>@endif
            @if (in_array('grade', $supportedFilters, true))<div class="dpe-dashboard-field"><label for="export-grade">Kelas sekolah</label><input id="export-grade" name="grade" type="text" maxlength="255" value="{{ $filters['grade'] }}" placeholder="Sesuai kelas yang tersimpan"></div>@endif
            <div class="dpe-dashboard-field"><label for="export-q">Pencarian</label><input id="export-q" name="q" type="search" maxlength="100" value="{{ $filters['q'] }}" placeholder="Nama / isi rekap"></div>
            <div class="dpe-dashboard-field"><label for="export-status">Status {{ $filters['dataset'] === 'videos' ? 'laporan' : '' }}</label><select id="export-status" name="status"><option value="all">Semua status</option>@foreach ($statusLabels as $key => $label)<option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>@endforeach</select></div>
            @if (in_array('location', $supportedFilters, true))<div class="dpe-dashboard-field"><label for="export-location">Tempat mengajar</label><select id="export-location" name="location"><option value="">Semua tempat</option><option value="branch" @selected($filters['location'] === 'branch')>Cabang</option><option value="student_home" @selected($filters['location'] === 'student_home')>Rumah siswa</option></select></div>@endif
            @if (in_array('mode', $supportedFilters, true))<div class="dpe-dashboard-field"><label for="export-mode">Mode absensi</label><select id="export-mode" name="mode"><option value="">Semua mode</option>@foreach (['branch_visit' => 'Kunjungan cabang', 'branch_day' => 'Ketidakhadiran cabang', 'private_session' => 'Private di rumah siswa', 'legacy' => 'Riwayat sesi lama'] as $key => $label)<option value="{{ $key }}" @selected($filters['mode'] === $key)>{{ $label }}</option>@endforeach</select></div>@endif
            <div class="dpe-export-filter__actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('exports.index', ['dataset' => $filters['dataset']]) }}">Reset</a></div>
        </form></div>
    </article>
    <article class="dpe-dashboard-card" id="export-preview">
        <header class="dpe-dashboard-card__head dpe-export-preview-head"><div><h2>Pratinjau hasil filter</h2><p><strong>{{ number_format($total, 0, ',', '.') }} baris sumber</strong>. Unduhan memuat seluruh hasil filter, termasuk halaman lain. Excel maksimal 3.000 baris; PDF maksimal 300 baris.</p></div>
            <div class="dpe-export-downloads">@foreach (['xlsx' => 'Unduh Excel', 'pdf' => 'Unduh PDF'] as $format => $label)
                @if ($total > 0 && ($format !== 'pdf' || $total <= \App\Services\OperationalExportDefinition::MAX_PDF_ROWS))<a class="dpe-dashboard-button {{ $format === 'xlsx' ? 'dpe-dashboard-button--primary' : 'dpe-dashboard-button--outline' }}" href="{{ route('exports.download', ['format' => $format] + $filters + ['expected_snapshot' => $snapshot]) }}">{{ $label }}</a>
                @else<span class="dpe-dashboard-button dpe-dashboard-button--outline dpe-export-unavailable" aria-disabled="true">{{ $label }}</span>@endif
            @endforeach</div>
        </header>
        @if ($total === 0)<x-dashboard.empty title="Belum ada data sesuai filter" message="Periksa rentang tanggal dan filter yang dipilih. Video dan perkembangan mengikuti periode laporan yang sudah disiapkan." icon="report" />
        @else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-export-table"><thead><tr><th>No.</th>@foreach ($columns as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead><tbody>
            @foreach ($pageRows as $row)<tr><td>{{ ($pageRows->currentPage() - 1) * 15 + $loop->iteration }}</td>@foreach ($columns as $column)@php($value = \App\Services\OperationalExportDefinition::display($row[$column['key']] ?? null, $column['type']))
                <td class="{{ $column['type'] === 'text' ? '' : 'dpe-export-number' }} {{ $column['key'] === 'status' ? 'dpe-export-status' : '' }}">@if (mb_strlen($value) > 240)<span>{{ \Illuminate\Support\Str::limit($value, 200) }}</span><details class="dpe-export-text"><summary>Lihat isi lengkap</summary><p>{{ $value }}</p></details>@else<span>{{ $value }}</span>@endif</td>
            @endforeach</tr>@endforeach
        </tbody></table></div><x-dashboard.pagination :paginator="$pageRows" />@endif
    </article>
@endsection
