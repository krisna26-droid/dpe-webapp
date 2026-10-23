@php
    $studentChallenge = $studentChallenge ?? null;
    $isEdit = $studentChallenge !== null;
    $formAction = $isEdit
        ? route('superadmin.student-challenges.update', $studentChallenge)
        : route('superadmin.student-challenges.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedStudent = old('student_id', $studentChallenge?->student_id ?: request('student_id'));
    $selectedTeacher = old('teacher_id', $studentChallenge?->teacher_id ?: request('teacher_id'));
    $selectedSession = old('session_id', $studentChallenge?->session_id ?: request('session_id'));
    $loggedOn = old('logged_on', $studentChallenge?->logged_on?->format('Y-m-d') ?: now()->format('Y-m-d'));
    $hasRequiredOptions = $students->isNotEmpty() && $teachers->isNotEmpty();
@endphp

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan.</span>
    </div>
@endif

@if (! $hasRequiredOptions)
    <div class="dpe-dashboard-alert dpe-dashboard-alert--warning" role="alert">
        <x-dashboard.icon name="info" />
        <span>Tambahkan minimal satu siswa aktif dan satu guru aktif sebelum mencatat tantangan.</span>
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
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @selected((string) $selectedStudent === (string) $student->id)>
                        {{ $student->full_name }}{{ $student->status === 'active' ? '' : ' (nonaktif)' }}
                        @if ($student->portalUser?->username) · {{ $student->portalUser->username }}@endif
                    </option>
                @endforeach
            </select>
            @if ($students->isEmpty())<p class="dpe-dashboard-field__hint">Belum ada siswa aktif yang dapat dipilih.</p>@endif
            @error('student_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

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
            @if ($teachers->isEmpty())<p class="dpe-dashboard-field__hint">Belum ada guru aktif yang dapat dipilih.</p>@endif
            @error('teacher_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="logged_on">Tanggal pencatatan</label>
            <input id="logged_on" name="logged_on" type="date" value="{{ $loggedOn }}" required>
            @error('logged_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="session_id">Sesi terkait <span>(opsional)</span></label>
            <select id="session_id" name="session_id">
                <option value="">Tidak ditautkan ke sesi</option>
                @foreach ($sessions as $session)
                    <option value="{{ $session->id }}" @selected((string) $selectedSession === (string) $session->id)>
                        {{ $session->planned_start_at?->format('d M Y H:i') ?: 'Tanpa waktu' }} · {{ $session->branch?->name ?: 'Tanpa cabang' }} · {{ $session->topic ?: 'Sesi belajar' }}
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Pilih sesi jika tantangan dicatat dari pertemuan yang sudah tercatat.</p>
            @error('session_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="category_name">Kategori tantangan</label>
            <input id="category_name" name="category_name" type="text" value="{{ old('category_name', $studentChallenge?->category_name) }}" placeholder="Contoh: Pronunciation" maxlength="255" required>
            <p class="dpe-dashboard-field__hint">Gunakan nama singkat yang mudah dipantau, misalnya Vocabulary atau Grammar.</p>
            @error('category_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="internal_note">Catatan internal <span>(opsional)</span></label>
            <textarea id="internal_note" name="internal_note" placeholder="Tuliskan contoh kendala, konteks, atau tindak lanjut yang disarankan">{{ old('internal_note', $studentChallenge?->internal_note) }}</textarea>
            @error('internal_note')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.student-challenges.show', $studentChallenge) : route('superadmin.student-challenges.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled(! $hasRequiredOptions)>
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan tantangan' }}</span>
        </button>
    </div>
</form>
