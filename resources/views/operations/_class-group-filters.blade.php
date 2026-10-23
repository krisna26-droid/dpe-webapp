@php($filterOptions = $classGroupFilterOptions)
<article class="dpe-dashboard-card dpe-class-group-filter-card">
    <header class="dpe-dashboard-card__head">
        <div>
            <h2>Filter kelompok kelas</h2>
            <p>{{ $filterOptions['showBranch'] ? 'Pilih cabang, kelompok, program, atau Teacher. Filter dapat digabungkan.' : 'Pilih kelompok, program, atau Teacher pada cabang yang Anda kelola.' }}</p>
        </div>
    </header>
    <div class="dpe-dashboard-card__body">
        @if ($filterOptions['branches']->isEmpty())
            <p class="dpe-dashboard-field__hint">{{ $filterOptions['showBranch'] ? 'Belum ada cabang. Tambahkan cabang terlebih dahulu.' : 'Belum ada penugasan cabang aktif. Hubungi Super Admin untuk menyiapkan penugasan Anda.' }}</p>
        @endif
        <form method="GET" action="{{ $filterOptions['action'] }}" class="dpe-class-group-filters">
            @if ($filterOptions['showBranch'])
                <div class="dpe-dashboard-field">
                    <label for="class-group-filter-branch">Cabang</label>
                    <select id="class-group-filter-branch" name="branch_id">
                        <option value="">Semua cabang</option>
                        @foreach ($filterOptions['branches'] as $branch)
                            <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="dpe-dashboard-field">
                <label for="class-group-filter-group">Kelompok</label>
                <select id="class-group-filter-group" name="class_group_id">
                    <option value="">Semua kelompok</option>
                    @foreach ($filterOptions['groups'] as $group)
                        <option value="{{ $group->id }}" @selected(($filters['class_group_id'] ?? '') === $group->id)>{{ $group->code }} — {{ $group->name }}{{ $filterOptions['showBranch'] ? ' · '.$group->branch?->name : '' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dpe-dashboard-field">
                <label for="class-group-filter-program">Program</label>
                <select id="class-group-filter-program" name="program_id">
                    <option value="">Semua program</option>
                    @foreach ($filterOptions['programs'] as $program)
                        <option value="{{ $program->id }}" @selected(($filters['program_id'] ?? '') === $program->id)>{{ $program->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dpe-dashboard-field">
                <label for="class-group-filter-teacher">Teacher</label>
                <select id="class-group-filter-teacher" name="teacher_id">
                    <option value="">Semua Teacher</option>
                    @foreach ($filterOptions['teachers'] as $teacher)
                        <option value="{{ $teacher->id }}" @selected(($filters['teacher_id'] ?? '') === $teacher->id)>{{ $teacher->user?->full_name ?: $teacher->user?->username ?: $teacher->id }}</option>
                    @endforeach
                </select>
            </div>
            <div class="dpe-dashboard-field">
                <label for="class-group-filter-query">Cari kode / nama kelompok</label>
                <input id="class-group-filter-query" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="120">
            </div>
            <div class="dpe-dashboard-field">
                <label for="class-group-filter-status">Status</label>
                <select id="class-group-filter-status" name="status">
                    <option value="">Semua status</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="dpe-class-group-filter-actions">
                <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button>
                <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $filterOptions['action'] }}">Reset</a>
            </div>
        </form>
    </div>
</article>
