@php
    $availability = $availability ?? null;
    $isEdit = $availability !== null;
    $formAction = $isEdit
        ? route('superadmin.teacher-availability.update', $availability)
        : route('superadmin.teacher-availability.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedTeacher = old('teacher_id', $availability?->teacher_id);
    $selectedBranch = old('branch_id', $availability?->branch_id);
    $availableOn = old('available_on', $availability?->available_on?->format('Y-m-d'));
    $startsAt = old('starts_at', substr((string) ($availability?->starts_at ?: '09:00:00'), 0, 8));
    $endsAt = old('ends_at', substr((string) ($availability?->ends_at ?: '11:00:00'), 0, 8));
    $classType = old('class_type', $availability?->class_type ?: 'Regular');
    $availabilityStatus = old('availability_status', $availability?->availability_status ?: 'Available');
    $classTypeOptions = ['Regular', 'Private'];
    $statusOptions = ['Available', 'Booked', 'Blocked'];
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
            <label for="available_on">Tanggal tersedia</label>
            <input id="available_on" name="available_on" type="date" value="{{ $availableOn }}" required>
            @error('available_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="class_type">Jenis kelas</label>
            <select id="class_type" name="class_type" required>
                @foreach ($classTypeOptions as $option)
                    <option value="{{ $option }}" @selected(strtolower((string) $classType) === strtolower($option))>{{ $option }}</option>
                @endforeach
                @if ($classType && !in_array(strtolower((string) $classType), array_map('strtolower', $classTypeOptions), true))
                    <option value="{{ $classType }}" selected>{{ $classType }} (tersimpan)</option>
                @endif
            </select>
            @error('class_type')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="starts_at">Waktu mulai</label>
            <input id="starts_at" name="starts_at" type="time" step="1" value="{{ $startsAt }}" required>
            @error('starts_at')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="ends_at">Waktu selesai</label>
            <input id="ends_at" name="ends_at" type="time" step="1" value="{{ $endsAt }}" required>
            @error('ends_at')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="availability_status">Status slot</label>
            <select id="availability_status" name="availability_status" required>
                @foreach ($statusOptions as $option)
                    <option value="{{ $option }}" @selected(strtolower((string) $availabilityStatus) === strtolower($option))>{{ $option }}</option>
                @endforeach
                @if ($availabilityStatus && !in_array(strtolower((string) $availabilityStatus), array_map('strtolower', $statusOptions), true))
                    <option value="{{ $availabilityStatus }}" selected>{{ $availabilityStatus }} (tersimpan)</option>
                @endif
            </select>
            @error('availability_status')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="notes">Catatan <span>(opsional)</span></label>
            <textarea id="notes" name="notes" maxlength="255" placeholder="Contoh: Guru hanya menerima kelas private pada slot ini.">{{ old('notes', $availability?->notes) }}</textarea>
            @error('notes')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.teacher-availability.show', $availability) : route('superadmin.teacher-availability.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan slot' }}</span>
        </button>
    </div>
</form>
