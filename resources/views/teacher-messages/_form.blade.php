@php
    $teacherMessage = $teacherMessage ?? null;
    $isEdit = $teacherMessage !== null;
    $formAction = $isEdit
        ? route('superadmin.teacher-messages.update', $teacherMessage)
        : route('superadmin.teacher-messages.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedStudent = old('student_id', $teacherMessage?->student_id);
    $selectedTeacher = old('teacher_id', $teacherMessage?->teacher_id);
    $selectedSession = old('session_id', $teacherMessage?->session_id);
    $selectedAttachment = old('attachment_file_id', $teacherMessage?->attachment_file_id);
@endphp

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan pesan.</span>
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
            @if ($students->isEmpty())
                <p class="dpe-dashboard-field__hint">Belum ada siswa aktif yang dapat dipilih.</p>
            @endif
            @error('student_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="teacher_id">Guru</label>
            <select id="teacher_id" name="teacher_id" required>
                <option value="">Pilih guru</option>
                @foreach ($teachers as $teacher)
                    @php
                        $teacherName = $teacher->user?->full_name ?: ($teacher->user?->username ?: $teacher->id);
                    @endphp
                    <option value="{{ $teacher->id }}" @selected((string) $selectedTeacher === (string) $teacher->id)>
                        {{ $teacherName }}{{ $teacher->is_active ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            @if ($teachers->isEmpty())
                <p class="dpe-dashboard-field__hint">Belum ada guru aktif yang dapat dipilih.</p>
            @endif
            @error('teacher_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="session_id">Sesi belajar <span>(opsional)</span></label>
            <select id="session_id" name="session_id">
                <option value="">Tidak ditautkan ke sesi</option>
                @foreach ($sessions as $session)
                    @php
                        $sessionTeacherName = $session->teacher?->user?->full_name
                            ?: ($session->teacher?->user?->username ?: 'Guru');
                        $sessionLabel = ($session->planned_start_at?->format('d M Y H:i') ?: 'Tanpa waktu')
                            . ' · '
                            . ($session->branch?->name ?: 'Tanpa cabang')
                            . ' · '
                            . ($session->program?->name ?: $session->topic ?: 'Sesi belajar')
                            . ' · '
                            . $sessionTeacherName;
                    @endphp
                    <option value="{{ $session->id }}" @selected((string) $selectedSession === (string) $session->id)>
                        {{ $sessionLabel }}
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Gunakan sesi jika pesan berkaitan dengan pertemuan tertentu.</p>
            @error('session_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="message_body">Isi pesan</label>
            <textarea id="message_body" name="message_body" placeholder="Tulis pesan, catatan perkembangan, atau tindak lanjut untuk siswa..." required>{{ old('message_body', $teacherMessage?->message_body) }}</textarea>
            <p class="dpe-dashboard-field__hint">Tulis informasi yang jelas dan relevan dengan proses belajar siswa.</p>
            @error('message_body')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="attachment_file_id">Lampiran tersimpan <span>(opsional)</span></label>
            <select id="attachment_file_id" name="attachment_file_id">
                <option value="">Tanpa lampiran</option>
                @foreach ($attachments as $attachment)
                    <option value="{{ $attachment->id }}" @selected((string) $selectedAttachment === (string) $attachment->id)>
                        {{ $attachment->original_name ?: 'File tanpa nama' }} · {{ $attachment->mime_type ?: 'Tipe file tidak tersedia' }}
                    </option>
                @endforeach
            </select>
            @if ($attachments->isEmpty())
                <p class="dpe-dashboard-field__hint">Belum ada file tersimpan yang dapat ditautkan. Lampiran dapat ditambahkan melalui alur upload file.</p>
            @else
                <p class="dpe-dashboard-field__hint">Pilih file yang sudah tersedia di penyimpanan aplikasi jika pesan memiliki dokumen pendukung.</p>
            @endif
            @error('attachment_file_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.teacher-messages.show', $teacherMessage) : route('superadmin.teacher-messages.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan pesan' }}</span>
        </button>
    </div>
</form>
