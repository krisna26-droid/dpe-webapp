@if (isset($operationsFilterOptions))
    @php($filterOptions = $operationsFilterOptions)
    <article class="dpe-dashboard-card dpe-branch-filter-card">
        <header class="dpe-dashboard-card__head"><div><h2>Filter {{ $filterOptions['kind'] === 'sessions' ? 'jadwal' : ($filterOptions['kind'] === 'memberships' ? 'peserta' : 'kelas') }}</h2><p>Data mengikuti cabang yang dapat Anda kelola.</p></div></header>
        <div class="dpe-dashboard-card__body">
            @if ($filterOptions['branches']->isEmpty())
                <p class="dpe-dashboard-field__hint">Belum ada penugasan cabang aktif. Hubungi Super Admin untuk menyiapkan penugasan Anda.</p>
            @endif
            <form method="GET" action="{{ $filterOptions['action'] }}" class="dpe-branch-filters">
                <div class="dpe-dashboard-field"><label for="branch-query">Cari {{ $filterOptions['kind'] === 'memberships' ? 'siswa / kelas' : ($filterOptions['kind'] === 'sessions' ? 'Teacher / kelas / topik' : 'kode / nama kelas') }}</label><input id="branch-query" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="120"></div>
                <div class="dpe-dashboard-field"><label for="filter-branch">Cabang</label><select id="filter-branch" name="branch_id"><option value="">Semua cabang saya</option>@foreach($filterOptions['branches'] as $branch)<option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                <div class="dpe-dashboard-field"><label for="filter-program">Program</label><select id="filter-program" name="program_id"><option value="">Semua program</option>@foreach($filterOptions['programs'] as $program)<option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') === $program->id)>{{ $program->name }}</option>@endforeach</select></div>
                <div class="dpe-dashboard-field"><label for="filter-teacher">Teacher</label><select id="filter-teacher" name="teacher_id"><option value="">Semua Teacher</option>@foreach($filterOptions['teachers'] as $teacher)<option value="{{ $teacher->id }}" @selected(($filters['teacher_id'] ?? '') === $teacher->id)>{{ $teacher->user?->full_name ?: $teacher->user?->username ?: 'Teacher' }}</option>@endforeach</select></div>
                <div class="dpe-dashboard-field"><label for="filter-status">Status</label><select id="filter-status" name="status"><option value="">Semua status</option>@foreach(($filterOptions['kind'] === 'sessions' ? ['scheduled' => 'Terjadwal', 'ongoing' => 'Berlangsung', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'] : ['active' => 'Aktif hari ini', 'inactive' => 'Tidak aktif hari ini']) as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
                @if($filterOptions['kind'] === 'sessions')
                    <div class="dpe-dashboard-field"><label for="filter-from">Dari tanggal</label><input id="filter-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}"></div>
                    <div class="dpe-dashboard-field"><label for="filter-to">Sampai tanggal</label><input id="filter-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}"></div>
                @endif
                <div class="dpe-branch-filter-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $filterOptions['action'] }}">Reset</a></div>
            </form>
        </div>
    </article>
@endif
