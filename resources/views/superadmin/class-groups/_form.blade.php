@php
    $operationRoutePrefix = $operationRoutePrefix ?? 'superadmin';
@endphp
@php
    $classGroup = $classGroup ?? null;
    $isEdit = $classGroup !== null;
    $formAction = $isEdit
        ? route($operationRoutePrefix.'.class-groups.update', $classGroup)
        : route($operationRoutePrefix.'.class-groups.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $activeValue = old('is_active', $isEdit ? (int) $classGroup->is_active : 1);
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
        <div class="dpe-dashboard-field">
            <label for="code">Kode kelompok</label>
            <input id="code" name="code" type="text" value="{{ old('code', $classGroup?->code) }}" placeholder="Contoh: KLS-REG-A" maxlength="255" required>
            <p class="dpe-dashboard-field__hint">Kode unik pada cabang ini untuk pencarian dan jadwal.</p>
            @error('code')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="name">Nama kelompok</label>
            <input id="name" name="name" type="text" value="{{ old('name', $classGroup?->name) }}" placeholder="Contoh: Regular A" maxlength="255" required>
            @error('name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="branch_id">Cabang</label>
            <select id="branch_id" name="branch_id" required>
                <option value="">Pilih cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $classGroup?->branch_id ?? ($operationRoutePrefix === 'admin' && $branches->count() === 1 ? $branches->first()->id : null)) === (string) $branch->id)>
                        {{ $branch->name }} ({{ $branch->code }}){{ $branch->is_active ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Hanya cabang aktif yang dapat dipakai untuk data baru.</p>
            @error('branch_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="program_id">Program belajar</label>
            <select id="program_id" name="program_id" required>
                <option value="">Pilih program</option>
                @foreach ($programs as $program)
                    <option value="{{ $program->id }}" @selected((string) old('program_id', $classGroup?->program_id) === (string) $program->id)>
                        {{ $program->name }} ({{ $program->code }}){{ $program->is_active ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Program menentukan konteks pembelajaran kelompok.</p>
            @error('program_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="default_teacher_id">Teacher utama <span>(opsional)</span></label>
            <select id="default_teacher_id" name="default_teacher_id">
                <option value="">Belum ditentukan</option>
                @foreach ($teachers as $teacher)
                    @php($teacherName = $teacher->user?->full_name ?: ($teacher->user?->username ?: $teacher->id))
                    <option value="{{ $teacher->id }}" @if(isset($teacherBranches)) data-branches="{{ implode(' ', $teacherBranches[$teacher->id] ?? []) }}" @endif @selected((string) old('default_teacher_id', $classGroup?->default_teacher_id) === (string) $teacher->id)>
                        {{ $teacherName }}{{ $teacher->is_active ? '' : ' (nonaktif)' }}
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Teacher utama menjadi referensi awal; penugasan aktual dapat berkembang pada modul jadwal.</p>
            @error('default_teacher_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <div class="dpe-dashboard-checkbox">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" name="is_active" type="checkbox" value="1" @checked($activeValue)>
                <label for="is_active">Kelompok aktif dan dapat menerima peserta baru</label>
            </div>
            @error('is_active')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route($operationRoutePrefix.'.class-groups.show', $classGroup) : route($operationRoutePrefix.'.class-groups.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan kelompok' }}</span>
        </button>
    </div>
</form>
