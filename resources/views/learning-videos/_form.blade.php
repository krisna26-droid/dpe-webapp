@php
    $learningVideo = $learningVideo ?? null;
    $isEdit = $learningVideo !== null;
    $formAction = $isEdit
        ? route('superadmin.learning-videos.update', $learningVideo)
        : route('superadmin.learning-videos.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedStudent = old('student_id', $learningVideo?->student_id);
    $selectedTeacher = old('teacher_id', $learningVideo?->teacher_id);
    $selectedSession = old('session_id', $learningVideo?->session_id);
    $videoOn = old('video_on', $learningVideo?->video_on?->format('Y-m-d') ?: now()->format('Y-m-d'));
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
            <label for="video_on">Tanggal video</label>
            <input id="video_on" name="video_on" type="date" value="{{ $videoOn }}" required>
            @error('video_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
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
            <p class="dpe-dashboard-field__hint">Pilih sesi jika video dibuat dari pertemuan yang sudah tercatat.</p>
            @error('session_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="topic">Topik video</label>
            <input id="topic" name="topic" type="text" value="{{ old('topic', $learningVideo?->topic) }}" placeholder="Contoh: Vocabulary practice" maxlength="255" required>
            @error('topic')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="video_url">Tautan video</label>
            <input id="video_url" name="video_url" type="url" value="{{ old('video_url', $learningVideo?->video_url) }}" placeholder="https://contoh.com/video" required>
            <p class="dpe-dashboard-field__hint">Masukkan tautan video yang dapat dibuka oleh pengguna yang berwenang.</p>
            @error('video_url')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="description">Deskripsi <span>(opsional)</span></label>
            <textarea id="description" name="description" placeholder="Ringkasan isi atau catatan video">{{ old('description', $learningVideo?->description) }}</textarea>
            @error('description')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.learning-videos.show', $learningVideo) : route('superadmin.learning-videos.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan video' }}</span>
        </button>
    </div>
</form>
