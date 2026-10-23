@extends('layouts.dashboard')
@section('title', 'Slot Ketersediaan Saya')
@section('heading', 'Slot Ketersediaan Saya')
@section('description', 'Catat waktu yang tersedia pada tanggal dan cabang tertentu.')

@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Teacher / Jadwal Mengajar</p><h2>Atur ketersediaan mengajar Anda.</h2><p>Admin memakai slot ini saat menyiapkan jadwal. Status slot diatur manual.</p></div><a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('teacher.availability.create') }}"><x-dashboard.icon name="plus" />Tambah slot</a></section>
    @include('teacher.workspace._tabs')
    <div class="dpe-teacher-stack">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Filter slot</h2><p>Pilih tanggal, cabang, atau status ketersediaan.</p></div></header>
            <div class="dpe-dashboard-card__body"><form method="GET" action="{{ route('teacher.availability.index') }}" class="dpe-teacher-filters">
                <div class="dpe-dashboard-field"><label for="slots-from">Dari tanggal</label><input id="slots-from" name="from" type="date" value="{{ $filters['from'] }}" min="2000-01-01" max="2100-12-31"></div>
                <div class="dpe-dashboard-field"><label for="slots-to">Sampai tanggal</label><input id="slots-to" name="to" type="date" value="{{ $filters['to'] }}" min="2000-01-01" max="2100-12-31"></div>
                <div class="dpe-dashboard-field"><label for="slots-branch">Cabang</label><select id="slots-branch" name="branch_id"><option value="">Semua cabang saya</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                <div class="dpe-dashboard-field"><label for="slots-status">Status</label><select id="slots-status" name="status"><option value="">Semua status</option>@foreach(\App\Services\TeacherWorkspaceService::SLOT_STATUSES as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="dpe-dashboard-field"><label for="slots-q">Catatan</label><input id="slots-q" name="q" type="search" maxlength="120" value="{{ $filters['q'] ?? '' }}" placeholder="Cari catatan slot"></div>
                <div class="dpe-teacher-filter-actions"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.availability.index') }}">Reset</a></div>
            </form></div>
        </article>
        <div class="dpe-teacher-notice"><p>Mengubah slot tidak memindahkan atau membatalkan sesi yang sudah dibuat. Jika ada perubahan jadwal, hubungi Admin cabang.</p></div>
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Slot saya</h2><p>Slot milik Teacher yang sedang login.</p></div><span class="dpe-dashboard-badge">{{ $slots->total() }} slot</span></header>
            @if($slots->isEmpty())<x-dashboard.empty title="Belum ada slot untuk filter ini" message="Tambahkan slot pada cabang penugasan Anda, atau coba rentang tanggal lain." icon="clock" />
            @else
                <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-teacher-table"><thead><tr><th>Tanggal</th><th>Waktu</th><th>Cabang</th><th>Jenis kelas</th><th>Status</th><th>Aksi</th></tr></thead><tbody>@foreach($slots as $slot)<tr><td>{{ $slot->available_on->format('d M Y') }}</td><td>{{ substr($slot->starts_at,0,5) }}–{{ substr($slot->ends_at,0,5) }}</td><td>{{ $slot->branch?->name }}</td><td>{{ ucfirst(strtolower($slot->class_type)) }}</td><td><span class="dpe-dashboard-badge">{{ \App\Services\TeacherWorkspaceService::SLOT_STATUSES[ucfirst(strtolower(trim($slot->availability_status)))] ?? $slot->availability_status }}</span></td><td><div class="dpe-operations-actions"><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('teacher.availability.show',$slot) }}">Detail</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.availability.edit',$slot) }}">Edit</a></div></td></tr>@endforeach</tbody></table></div><x-dashboard.pagination :paginator="$slots" />
            @endif
        </article>
    </div>
@endsection
