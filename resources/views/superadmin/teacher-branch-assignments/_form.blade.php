@php
    $assignment = $assignment ?? null;
    $isEdit = $assignment !== null;
    $formAction = $isEdit
        ? route('superadmin.teacher-branch-assignments.update', $assignment)
        : route('superadmin.teacher-branch-assignments.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedTeacher = old('teacher_id', $isEdit ? $assignment->teacher_id : request('teacher_id'));
    $selectedBranch = old('branch_id', $assignment?->branch_id);
    $startsOn = old('starts_on', $assignment?->starts_on?->format('Y-m-d'));
    $endsOn = old('ends_on', $assignment?->ends_on?->format('Y-m-d'));
@endphp

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan.</span>
    </div>
@endif

<form method="POST" action="{{ $formAction }}">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="dpe-dashboard-form-grid">
        <div class="dpe-dashboard-field">
            <label for="teacher_id">Guru</label>
            <select id="teacher_id" name="teacher_id" required>
                <option value="">Pilih guru</option>
                @foreach ($teachers as $teacher)
                    @php($teacherName = $teacher->user?->full_name ?: ($teacher->user?->username ?: $teacher->id))
                    <option value="{{ $teacher->id }}" @selected((string) $selectedTeacher === (string) $teacher->id)>
                        {{ $teacherName }}{{ $teacher->is_active ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Guru nonaktif hanya ditampilkan saat sedang terhubung dengan data lama.</p>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('admin.teachers.create') }}">
                <x-dashboard.icon name="plus" />
                <span>Buat profil guru</span>
            </a>
            @if ($teachers->isEmpty())
                <p class="dpe-dashboard-field__error">Belum ada profil guru aktif. Buat profil guru terlebih dahulu dari akun role Guru.</p>
            @endif
            @error('teacher_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="branch_id">Cabang</label>
            <select id="branch_id" name="branch_id" required>
                <option value="">Pilih cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>
                        {{ $branch->name }} ({{ $branch->code }}){{ $branch->is_active ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            @error('branch_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="starts_on">Tanggal mulai</label>
            <input id="starts_on" name="starts_on" type="date" value="{{ $startsOn }}" required>
            @error('starts_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="ends_on">Tanggal berakhir <span>(opsional)</span></label>
            <input id="ends_on" name="ends_on" type="date" value="{{ $endsOn }}">
            <p class="dpe-dashboard-field__hint">Kosongkan jika penugasan masih berjalan.</p>
            @error('ends_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.teacher-branch-assignments.show', $assignment) : route('superadmin.teacher-branch-assignments.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($teachers->isEmpty() || $branches->isEmpty())>
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan penugasan' }}</span>
        </button>
    </div>
</form>
