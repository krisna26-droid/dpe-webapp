@php
    $assignment = $assignment ?? null;
    $isEdit = $assignment !== null;
    $selectedStudent = old('student_id', $assignment?->student_id ?? request('student_id'));
    $selectedTeacher = old('teacher_id', $assignment?->teacher_id ?? request('teacher_id'));
@endphp
<form method="POST" action="{{ $isEdit ? route('superadmin.student-teacher-assignments.update', $assignment) : route('superadmin.student-teacher-assignments.store') }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <div class="dpe-dashboard-form-grid">
        <div class="dpe-dashboard-field">
            <label for="student_id">Siswa</label>
            <select id="student_id" name="student_id" required><option value="">Pilih siswa</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @selected((string) $selectedStudent === $student->id)>{{ $student->full_name }}{{ $student->status === 'active' ? '' : ' (nonaktif)' }}</option>
                @endforeach
            </select>
            @error('student_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
        <div class="dpe-dashboard-field">
            <label for="teacher_id">Teacher</label>
            <select id="teacher_id" name="teacher_id" required><option value="">Pilih Teacher</option>
                @foreach ($teachers as $teacher)
                    <option value="{{ $teacher->id }}" @selected((string) $selectedTeacher === $teacher->id)>{{ $teacher->user?->full_name ?: $teacher->id }}{{ $teacher->is_active && $teacher->user?->is_active ? '' : ' (nonaktif)' }}</option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Penugasan baru memakai profil dan akun Teacher aktif.</p>
            @error('teacher_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
        <div class="dpe-dashboard-field">
            <label for="starts_on">Tanggal mulai</label><input id="starts_on" name="starts_on" type="date" value="{{ old('starts_on', $assignment?->starts_on?->format('Y-m-d')) }}" required>
            @error('starts_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
        <div class="dpe-dashboard-field">
            <label for="ends_on">Tanggal berakhir <span>(opsional)</span></label><input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on', $assignment?->ends_on?->format('Y-m-d')) }}">
            @error('ends_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <div class="dpe-dashboard-checkbox"><input type="hidden" name="is_report_owner" value="0"><input id="is_report_owner" name="is_report_owner" type="checkbox" value="1" @checked(filter_var(old('is_report_owner', $assignment?->is_report_owner ?? false), FILTER_VALIDATE_BOOLEAN))><label for="is_report_owner">Prioritaskan sebagai pemilik laporan</label></div>
            <p class="dpe-dashboard-field__hint">Teacher pengajar tetap dapat melihat siswa yang ditugaskan. Opsi ini menyarankan pemilik laporan saat periode disiapkan dan tidak memindahkan laporan yang sudah dibuat.</p>
            @error('is_report_owner')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>
    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.student-teacher-assignments.index') }}">Kembali ke Penugasan Siswa</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($students->isEmpty() || $teachers->isEmpty())><x-dashboard.icon name="check" /> {{ $isEdit ? 'Simpan perubahan' : 'Simpan penugasan' }}</button>
    </div>
</form>
