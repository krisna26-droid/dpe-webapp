@extends('layouts.dashboard')
@section('title', 'Tambah Jadwal Berulang')
@section('heading', 'Jadwal & Sesi')
@section('description', 'Siapkan seluruh pertemuan mingguan untuk satu kelompok kelas dalam satu kali penyimpanan.')
@push('head') @include('lesson-series._head') @endpush
@push('scripts')<script src="{{ asset('assets/dpe/branch-operations.js') }}?v={{ filemtime(public_path('assets/dpe/branch-operations.js')) }}" defer></script>@endpush
@section('content')
    @php($formValue = fn($field, $default = null) => array_key_exists($field, $formValues) ? $formValues[$field] : $default)
    <x-dashboard.session-mode :prefix="$operationRoutePrefix" :weekly="true" />
    <x-dashboard.form-panel title="Jadwal berulang mingguan" description="Pilih rentang hingga tiga bulan. Sistem mencari tanggal berdasarkan hari, sehingga pergantian bulan mengikuti kalender." :close-url="route($operationRoutePrefix.'.lesson-sessions.index')">
        @if($errors->any())<div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <form method="POST" action="{{ route($operationRoutePrefix.'.lesson-series.preview') }}" data-branch-operations-form>
            @csrf
            @if($formDraftId)<input type="hidden" name="draft_id" value="{{ $formDraftId }}">@endif
            <div class="dpe-dashboard-form-grid dpe-dashboard-form-grid--three">
                <div class="dpe-dashboard-field"><label for="branch_id">Cabang</label><select id="branch_id" name="branch_id" required><option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($formValue('branch_id', $branches->count() === 1 ? $branches->first()->id : '') === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                <div class="dpe-dashboard-field"><label for="teacher_id">Teacher</label><select id="teacher_id" name="teacher_id" required><option value="">Pilih Teacher</option>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" data-branches="{{ implode(' ', $teacherBranches[$teacher->id] ?? []) }}" @selected($formValue('teacher_id') === $teacher->id)>{{ $teacher->user?->full_name ?: $teacher->user?->username }}</option>@endforeach</select></div>
                <div class="dpe-dashboard-field"><label for="program_id">Program</label><select id="program_id" name="program_id" required><option value="">Pilih program</option>@foreach($programs as $program)<option value="{{ $program->id }}" @selected($formValue('program_id') === $program->id)>{{ $program->name }}</option>@endforeach</select></div>
                <div class="dpe-dashboard-field"><label for="class_group_id">Kelompok kelas</label><select id="class_group_id" name="class_group_id" required><option value="">Pilih satu kelompok</option>@foreach($classGroups as $group)<option value="{{ $group->id }}" data-branch="{{ $group->branch_id }}" data-program="{{ $group->program_id }}" data-teacher="{{ $group->default_teacher_id }}" @selected($formValue('class_group_id') === $group->id)>{{ $group->name }} · {{ $group->branch?->name }}</option>@endforeach</select><p class="dpe-dashboard-field__hint">Satu seri untuk satu kelompok dan satu pasangan jam. Buat seri lain untuk kelas atau jam berbeda.</p></div>
                <div class="dpe-dashboard-field"><label for="teaching_location">Lokasi mengajar</label><select id="teaching_location" name="teaching_location" required><option value="branch" @selected($formValue('teaching_location', 'branch') === 'branch')>Di cabang</option><option value="student_home" @selected($formValue('teaching_location') === 'student_home')>Di rumah siswa</option></select></div>
                <div class="dpe-dashboard-field"><label for="starts_on">Tanggal awal</label><input id="starts_on" name="starts_on" type="date" required value="{{ $formValue('starts_on', today()->toDateString()) }}"></div>
                <div class="dpe-dashboard-field"><label for="ends_on">Tanggal akhir</label><input id="ends_on" name="ends_on" type="date" required value="{{ $formValue('ends_on', today()->addMonthsNoOverflow(3)->subDay()->toDateString()) }}"><p class="dpe-dashboard-field__hint">Maksimal tiga bulan sejak tanggal awal.</p></div>
                <div class="dpe-dashboard-field"><label for="starts_at">Jam mulai</label><input id="starts_at" name="starts_at" type="time" required value="{{ $formValue('starts_at') }}"></div>
                <div class="dpe-dashboard-field"><label for="ends_at">Jam selesai</label><input id="ends_at" name="ends_at" type="time" required value="{{ $formValue('ends_at') }}"><p class="dpe-dashboard-field__hint">Jam mengikuti zona waktu cabang pada setiap tanggal.</p></div>
                <fieldset class="dpe-dashboard-field dpe-dashboard-field--full dpe-series-days"><legend>Hari berulang</legend><div>@foreach($dayLabels as $number => $label)<label><input type="checkbox" name="weekdays[]" value="{{ $number }}" @checked(in_array((string) $number, array_map('strval', (array) $formValue('weekdays', [])), true))> {{ $label }}</label>@endforeach</div><p class="dpe-dashboard-field__hint">Boleh memilih beberapa hari jika kelompok dan jamnya sama. Tanggal libur dapat dikecualikan pada pratinjau.</p></fieldset>
                <div class="dpe-dashboard-field"><label for="topic">Topik awal <span>(opsional)</span></label><input id="topic" name="topic" maxlength="255" value="{{ $formValue('topic') }}"></div>
                <div class="dpe-dashboard-field"><label for="material">Materi awal <span>(opsional)</span></label><input id="material" name="material" maxlength="255" value="{{ $formValue('material') }}"></div>
                <div class="dpe-dashboard-field"><label for="activity">Aktivitas awal <span>(opsional)</span></label><input id="activity" name="activity" maxlength="255" value="{{ $formValue('activity') }}"></div>
            </div>
            <p class="dpe-dashboard-field__hint">Semua pertemuan baru berstatus Terjadwal. Topik, materi dan aktivitas awal diterapkan pada pertemuan yang dibuat dan dapat dilengkapi per pertemuan.</p>
            <div class="dpe-dashboard-form-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.lesson-sessions.index') }}">Batal</a><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Pratinjau jadwal</button></div>
        </form>
    </x-dashboard.form-panel>
@endsection
