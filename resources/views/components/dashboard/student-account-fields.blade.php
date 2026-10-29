@php
    $branchValue=old('branch_id',$branches->count()===1?$branches->first()->id:'');
    $teacherValue=old('teacher_id','');
    $selectedSlots=(array)old('learning_session_ids',[]);
@endphp
<div class="dpe-dashboard-form-grid" data-student-account>
    <div class="dpe-dashboard-field"><label for="student_username">Username</label><input id="student_username" name="username" value="{{ old('username') }}" maxlength="255" autocomplete="off" autocapitalize="none" spellcheck="false" required>@error('username')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
    <div class="dpe-dashboard-field"><label for="student_full_name">Nama siswa</label><input id="student_full_name" name="full_name" value="{{ old('full_name') }}" maxlength="255" required>@error('full_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
    <div class="dpe-dashboard-field"><label for="student_password">Password</label><div class="dpe-account-password"><input id="student_password" name="password" type="password" minlength="8" maxlength="255" autocomplete="new-password" required><button type="button" class="dpe-account-password-toggle" data-account-toggle="student_password" data-account-label="password" aria-controls="student_password" aria-label="Tampilkan password" aria-pressed="false" hidden><x-dpe.icon name="eye" /></button></div><p class="dpe-dashboard-field__hint">Minimal 8 karakter.</p>@error('password')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
    <div class="dpe-dashboard-field"><label for="student_grade_name">Kelas</label><input id="student_grade_name" name="grade_name" value="{{ old('grade_name') }}" maxlength="255" placeholder="Contoh: Kelas 5 SD" required><p class="dpe-dashboard-field__hint">Kelas siswa di sekolah. Kelompok DPE mengikuti sesi belajar yang dipilih.</p>@error('grade_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
    <div class="dpe-dashboard-field"><label for="student_school_name">Asal sekolah</label><input id="student_school_name" name="school_name" value="{{ old('school_name') }}" maxlength="255" required>@error('school_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
    <div class="dpe-dashboard-field"><label for="student_guardian_phone">Nomor telepon wali siswa</label><input id="student_guardian_phone" name="guardian_phone" type="tel" value="{{ old('guardian_phone') }}" maxlength="30" autocomplete="tel" required>@error('guardian_phone')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
    <div class="dpe-dashboard-field"><label for="student_branch_id">Cabang</label><select id="student_branch_id" name="branch_id" data-account-branch required><option value="">Pilih cabang</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($branchValue===$branch->id)>{{ $branch->name }}</option>@endforeach</select>@error('branch_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
    <div class="dpe-dashboard-field"><label for="student_teacher_id">Nama guru</label><select id="student_teacher_id" name="teacher_id" data-account-teacher required><option value="">Pilih guru</option>@foreach($accountTeachers as $teacher)<option value="{{ $teacher->id }}" data-branches="{{ implode(' ',array_unique($accountTeacherBranches[$teacher->id]??[])) }}" @selected($teacherValue===$teacher->id)>{{ $teacher->user?->full_name ?: $teacher->user?->username }}</option>@endforeach</select>@error('teacher_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
    <fieldset class="dpe-session-choices dpe-dashboard-field--full"><legend>Sesi belajar</legend><p class="dpe-dashboard-field__hint">Pilih hari dan sesi belajar siswa. Boleh memilih beberapa sesi pada hari yang sama. Pilihan harus berasal dari satu program.</p>
        <div class="dpe-session-days">
            @foreach($accountSessionChoices->groupBy(fn($choice) => $choice['weekday'] ?? 0) as $dayChoices)
                <div class="dpe-session-day" data-account-day>
                    <h3 class="dpe-session-day__title">{{ $dayChoices->first()['day_name'] }}</h3>
                    @foreach($dayChoices as $choice)
                        <label class="dpe-session-choice" data-account-slot data-branch="{{ $choice['session']->classGroup->branch_id }}" data-teachers="{{ implode(' ', $choice['teacher_ids']) }}">
                            <input type="checkbox" name="learning_session_ids[]" value="{{ $choice['key'] }}"
                                @checked(in_array($choice['key'], $selectedSlots, true) || in_array($choice['id'], $selectedSlots, true))>
                            <span><strong>{{ $choice['session']->name }} · {{ $choice['session']->classGroup->name }}</strong><small>{{ $choice['details'] }} · {{ $choice['session']->classGroup->program?->name }}</small></span>
                        </label>
                    @endforeach
                </div>
            @endforeach
        </div>
        <p class="dpe-dashboard-field__hint" data-account-empty role="status">Pilih cabang dan guru. Jika sesi belum tersedia, buat jadwal kelompok terlebih dahulu.</p>
        @error('learning_session_ids')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
    </fieldset>
</div>
