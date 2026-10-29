@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
    $lessonList = app(\App\Services\LessonSessionListService::class);
    $listParameters = $lessonList->parameters(request());
@endphp
@php
    $lessonSession = $lessonSession ?? null;
    $isEdit = $lessonSession !== null;
    $inSeries = (bool) $lessonSession?->schedule_series_id;
    $formAction = $isEdit
        ? route($operationRoutePrefix.'.lesson-sessions.update', [$lessonSession] + $listParameters)
        : route($operationRoutePrefix.'.lesson-sessions.store', $listParameters);
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedBranch = old('branch_id', $lessonSession?->branch_id ?? ($operationRoutePrefix === 'admin' && $branches->count() === 1 ? $branches->first()->id : null));
    $selectedTeacher = old('teacher_id', $lessonSession?->teacher_id);
    $selectedProgram = old('program_id', $lessonSession?->program_id);
    $selectedClassGroup = old('class_group_id', $lessonSession?->class_group_id);
    if ($inSeries) {
        $selectedBranch = $lessonSession->branch_id;
        $selectedProgram = $lessonSession->program_id;
        $selectedClassGroup = $lessonSession->class_group_id;
    }
    $selectedRescheduled = old('rescheduled_from_session_id', $lessonSession?->rescheduled_from_session_id);
    $plannedStart = old('planned_start_at', $lessonSession?->planned_start_at?->format('Y-m-d\TH:i'));
    $plannedEnd = old('planned_end_at', $lessonSession?->planned_end_at?->format('Y-m-d\TH:i'));
    $actualStart = old('actual_start_at', $lessonSession?->actual_start_at?->format('Y-m-d\TH:i'));
    $actualEnd = old('actual_end_at', $lessonSession?->actual_end_at?->format('Y-m-d\TH:i'));
    $selectedStatus = old('status', $lessonSession?->status ?: 'scheduled');
    $statusOptions = ['scheduled', 'ongoing', 'completed', 'cancelled'];
@endphp

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan.</span>
    </div>
@endif

@if($isEdit && $lessonSession->schedule_series_id)
    <div class="dpe-dashboard-form-note"><p>Perubahan pada form ini berlaku untuk <strong>pertemuan ini saja</strong>. Cabang, program dan kelompok tetap mengikuti seri.</p><div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.lesson-series.show', [$lessonSession->schedule_series_id] + $listParameters) }}">Lihat seluruh seri</a>@if($lessonSession->planned_start_at->isFuture() && $lessonSession->status === 'scheduled')<a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.lesson-series.edit', [$lessonSession->schedule_series_id, $lessonSession] + $listParameters) }}">Pertemuan ini dan berikutnya</a>@endif</div></div>
@endif

<form method="POST" action="{{ $formAction }}" @if($operationRoutePrefix === 'admin') data-branch-operations-form @endif>
    @csrf
    @if(isset($recordVersion) && $recordVersion)<input type="hidden" name="record_version" value="{{ old('record_version', $recordVersion) }}">@endif
    @error('record_version')<p class="dpe-dashboard-field__error dpe-branch-record-notice" role="alert">{{ $message }}</p>@enderror
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="dpe-dashboard-form-grid dpe-dashboard-form-grid--three">
        <div class="dpe-dashboard-field">
            <label for="branch_id">Cabang</label>
            <select id="branch_id" name="branch_id" required @disabled($inSeries)>
                <option value="">Pilih cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>
                        {{ $branch->name }} ({{ $branch->code }}){{ $branch->is_active ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            @if($inSeries)<input type="hidden" name="branch_id" value="{{ $selectedBranch }}">@endif
            @error('branch_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="teacher_id">Guru</label>
            <select id="teacher_id" name="teacher_id" required>
                <option value="">Pilih guru</option>
                @foreach ($teachers as $teacher)
                    @php($teacherName = $teacher->user?->full_name ?: ($teacher->user?->username ?: $teacher->id))
                    <option value="{{ $teacher->id }}" @if(isset($teacherBranches)) data-branches="{{ implode(' ', $teacherBranches[$teacher->id] ?? []) }}" @endif @selected((string) $selectedTeacher === (string) $teacher->id)>
                        {{ $teacherName }}{{ $teacher->is_active ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            @error('teacher_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
            @if($operationRoutePrefix === 'admin')<p class="dpe-dashboard-field__hint">Teacher mengikuti penugasan cabang, siswa, kelas, atau sesi. Penugasan diperiksa kembali untuk tanggal pertemuan.</p>@endif
        </div>

        <div class="dpe-dashboard-field">
            <label for="program_id">Program</label>
            <select id="program_id" name="program_id" required @disabled($inSeries)>
                <option value="">Pilih program</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected((string) $selectedProgram === (string) $program->id)>
                        {{ $program->name }}{{ $program->code ? ' ('.$program->code.')' : '' }}{{ $program->is_active ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            @if($inSeries)<input type="hidden" name="program_id" value="{{ $selectedProgram }}">@endif
            @error('program_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="class_group_id">Kelompok kelas <span>(opsional)</span></label>
            <select id="class_group_id" name="class_group_id" @disabled($inSeries)>
                <option value="">Tanpa kelompok kelas</option>
                @foreach ($classGroups as $classGroup)
                    <option value="{{ $classGroup->id }}" data-branch="{{ $classGroup->branch_id }}" data-program="{{ $classGroup->program_id }}" data-teacher="{{ $classGroup->default_teacher_id }}" @selected((string) $selectedClassGroup === (string) $classGroup->id)>
                        {{ $classGroup->name }}{{ $classGroup->code ? ' ('.$classGroup->code.')' : '' }}
                        @if ($classGroup->branch?->name) · {{ $classGroup->branch->name }}@endif
                    </option>
                @endforeach
            </select>
            @if($inSeries)<input type="hidden" name="class_group_id" value="{{ $selectedClassGroup }}">@endif
            <p class="dpe-dashboard-field__hint">{{ $operationRoutePrefix === 'admin' ? 'Kelas harus sesuai cabang dan program. Memilih kelas akan menyesuaikan program.' : 'Pilihan ini tidak mengubah program yang sudah dipilih.' }}</p>
            @error('class_group_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <x-dashboard.learning-session-selector :current="$lessonSession" />

        <div class="dpe-dashboard-field">
            <label for="teaching_location">Lokasi mengajar</label>
            <select id="teaching_location" name="teaching_location" required>
                <option value="branch" @selected(old('teaching_location', $lessonSession?->teaching_location ?? 'branch') === 'branch')>Di cabang · absensi per kunjungan</option>
                <option value="student_home" @selected(old('teaching_location', $lessonSession?->teaching_location) === 'student_home')>Private di rumah siswa · absensi per sesi</option>
            </select>
            <p class="dpe-dashboard-field__hint">Pilih lokasi pelaksanaan. Jenis program tidak menentukan lokasi secara otomatis.</p>
            @error('teaching_location')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="planned_start_at">Mulai rencana</label>
            <input id="planned_start_at" name="planned_start_at" type="datetime-local" value="{{ $plannedStart }}" required>
            @error('planned_start_at')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="planned_end_at">Selesai rencana</label>
            <input id="planned_end_at" name="planned_end_at" type="datetime-local" value="{{ $plannedEnd }}" required>
            @error('planned_end_at')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="actual_start_at">Mulai aktual <span>(opsional)</span></label>
            <input id="actual_start_at" name="actual_start_at" type="datetime-local" value="{{ $actualStart }}">
            @error('actual_start_at')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="actual_end_at">Selesai aktual <span>(opsional)</span></label>
            <input id="actual_end_at" name="actual_end_at" type="datetime-local" value="{{ $actualEnd }}">
            @error('actual_end_at')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="status">Status sesi</label>
            <select id="status" name="status" required>
                @foreach ($statusOptions as $option)
                    <option value="{{ $option }}" @selected(strtolower((string) $selectedStatus) === strtolower($option))>{{ ucwords(str_replace('_', ' ', $option)) }}</option>
                @endforeach
                @if ($selectedStatus && !in_array(strtolower((string) $selectedStatus), array_map('strtolower', $statusOptions), true))
                    <option value="{{ $selectedStatus }}" selected>{{ ucwords(str_replace('_', ' ', $selectedStatus)) }} (tersimpan)</option>
                @endif
            </select>
            @error('status')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="rescheduled_from_session_id">Sesi asal penjadwalan ulang <span>(opsional)</span></label>
            <select id="rescheduled_from_session_id" name="rescheduled_from_session_id">
                <option value="">Bukan penjadwalan ulang</option>
                @foreach ($reschedulableSessions as $reschedulableSession)
                    <option value="{{ $reschedulableSession->id }}" data-branch="{{ $reschedulableSession->branch_id }}" data-program="{{ $reschedulableSession->program_id }}" data-group="{{ $reschedulableSession->class_group_id }}" @selected((string) $selectedRescheduled === (string) $reschedulableSession->id)>
                        {{ $reschedulableSession->planned_start_at?->format('d M Y H:i') ?: 'Tanpa waktu' }}
                        · {{ $reschedulableSession->branch?->name ?: 'Tanpa cabang' }}
                        · {{ $reschedulableSession->program?->name ?: 'Tanpa program' }}
                    </option>
                @endforeach
            </select>
            @error('rescheduled_from_session_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="topic">Topik <span>(opsional)</span></label>
            <input id="topic" name="topic" type="text" maxlength="255" value="{{ old('topic', $lessonSession?->topic) }}" placeholder="Contoh: Daily conversation">
            @error('topic')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="material">Materi <span>(opsional)</span></label>
            <input id="material" name="material" type="text" maxlength="255" value="{{ old('material', $lessonSession?->material) }}" placeholder="Contoh: Worksheet unit 3">
            @error('material')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="activity">Aktivitas <span>(opsional)</span></label>
            <input id="activity" name="activity" type="text" maxlength="255" value="{{ old('activity', $lessonSession?->activity) }}" placeholder="Contoh: Role play">
            @error('activity')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route($operationRoutePrefix.'.lesson-sessions.show', [$lessonSession] + $listParameters) : route($operationRoutePrefix.'.lesson-sessions.index', $lessonList->backParameters(request())) }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan sesi' }}</span>
        </button>
    </div>
</form>
@include('operations._branch-availability')
