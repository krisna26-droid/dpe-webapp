@php
    $enrollment = $enrollment ?? null;
    $isEdit = $enrollment !== null;
    $formAction = $isEdit
        ? route('superadmin.student-enrollments.update', $enrollment)
        : route('superadmin.student-enrollments.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedStudent = old('student_id', $enrollment?->student_id ?: request('student_id'));
    $selectedBranch = old('branch_id', $enrollment?->branch_id);
    $selectedProgram = old('program_id', $enrollment?->program_id);
    $startsOn = old('starts_on', $enrollment?->starts_on?->format('Y-m-d'));
    $endsOn = old('ends_on', $enrollment?->ends_on?->format('Y-m-d'));
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
            <label for="student_id">Siswa</label>
            <select id="student_id" name="student_id" required>
                <option value="">Pilih siswa</option>
                @foreach ($students as $studentOption)
                    <option value="{{ $studentOption->id }}" @selected((string) $selectedStudent === (string) $studentOption->id)>
                        {{ $studentOption->full_name }}{{ $studentOption->portalUser?->username ? ' · '.$studentOption->portalUser->username : '' }}
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Hanya profil siswa aktif yang dapat menerima pendaftaran baru.</p>
            @error('student_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="program_id">Program belajar</label>
            <select id="program_id" name="program_id" required>
                <option value="">Pilih program</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected((string) $selectedProgram === (string) $program->id)>
                        {{ $program->name }} ({{ $program->code }}){{ $program->is_active ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            @error('program_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
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
            <p class="dpe-dashboard-field__hint">Kosongkan jika pendaftaran masih berjalan.</p>
            @error('ends_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.student-enrollments.show', $enrollment) : route('superadmin.student-enrollments.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan pendaftaran' }}</span>
        </button>
    </div>
</form>
