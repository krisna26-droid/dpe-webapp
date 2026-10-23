@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@php
    $membership = $classGroupMembership ?? null;
    $isEdit = $membership !== null;
    $formAction = $isEdit
        ? route($operationRoutePrefix.'.class-group-memberships.update', $membership)
        : route($operationRoutePrefix.'.class-group-memberships.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedClassGroup = old('class_group_id', $membership?->class_group_id ?: request('class_group_id'));
    $selectedStudent = old('student_id', $membership?->student_id);
    $startsOn = old('starts_on', $membership?->starts_on?->format('Y-m-d'));
    $endsOn = old('ends_on', $membership?->ends_on?->format('Y-m-d'));
@endphp

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan.</span>
    </div>
@endif

<form method="POST" action="{{ $formAction }}" @if($operationRoutePrefix === 'admin') data-branch-operations-form @endif>
    @csrf
    @if(isset($recordVersion) && $recordVersion)<input type="hidden" name="record_version" value="{{ old('record_version', $recordVersion) }}">@endif
    @error('record_version')<p class="dpe-dashboard-field__error dpe-branch-record-notice" role="alert">{{ $message }}</p>@enderror
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="dpe-dashboard-form-grid">
        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="class_group_id">Kelompok kelas</label>
            <select id="class_group_id" name="class_group_id" required>
                <option value="">Pilih kelompok kelas</option>
                @foreach ($classGroups as $classGroup)
                    <option value="{{ $classGroup->id }}" @selected((string) $selectedClassGroup === (string) $classGroup->id)>
                        {{ $classGroup->name }} ({{ $classGroup->code }}) · {{ $classGroup->branch?->name ?: 'Cabang belum tersedia' }}
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Kelompok nonaktif tidak tersedia untuk keanggotaan baru.</p>
            @error('class_group_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="student_id">Siswa</label>
            <select id="student_id" name="student_id" required>
                <option value="">Pilih siswa</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @if(isset($studentGroups)) data-class-groups="{{ implode(' ', $studentGroups[$student->id] ?? []) }}" @endif @selected((string) $selectedStudent === (string) $student->id)>
                        {{ $student->full_name }}{{ $student->school_name ? ' · '.$student->school_name : '' }}{{ $student->status !== 'active' ? ' (nonaktif)' : '' }}
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Siswa harus terdaftar pada cabang dan program kelas selama periode keanggotaan. Periode pada kelas yang sama tidak boleh bertumpang tindih.</p>
            @error('student_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="starts_on">Tanggal mulai</label>
            <input id="starts_on" name="starts_on" type="date" value="{{ $startsOn }}" required>
            <p class="dpe-dashboard-field__hint">Tanggal pertama siswa masuk ke kelompok.</p>
            @error('starts_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="ends_on">Tanggal berakhir <span>(opsional)</span></label>
            <input id="ends_on" name="ends_on" type="date" value="{{ $endsOn }}">
            <p class="dpe-dashboard-field__hint">Kosongkan jika keanggotaan masih berjalan.</p>
            @error('ends_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route($operationRoutePrefix.'.class-group-memberships.show', $membership) : route($operationRoutePrefix.'.class-group-memberships.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan anggota' }}</span>
        </button>
    </div>
</form>
