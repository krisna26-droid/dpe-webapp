@php
    $program = $program ?? null;
    $isEdit = $program !== null;
    $formAction = $isEdit
        ? route('superadmin.programs.update', $program)
        : route('superadmin.programs.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $activeValue = old('is_active', $isEdit ? (int) $program->is_active : 1);
    $selectedClassType = old('class_type', $program?->class_type ?: 'Regular');
    $classTypeOptions = ['Regular', 'Private', 'Intensive', 'Online'];
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
            <label for="code">Kode program</label>
            <input id="code" name="code" type="text" value="{{ old('code', $program?->code) }}" placeholder="Contoh: ENG-REG" maxlength="255" required>
            <p class="dpe-dashboard-field__hint">Kode unik untuk identifikasi program di data internal.</p>
            @error('code')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="name">Nama program</label>
            <input id="name" name="name" type="text" value="{{ old('name', $program?->name) }}" placeholder="Contoh: English Regular" maxlength="255" required>
            @error('name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="class_type">Tipe kelas</label>
            <select id="class_type" name="class_type" required>
                @foreach ($classTypeOptions as $option)
                    <option value="{{ $option }}" @selected(strtolower((string) $selectedClassType) === strtolower($option))>{{ $option }}</option>
                @endforeach
                @if ($selectedClassType && !in_array(strtolower((string) $selectedClassType), array_map('strtolower', $classTypeOptions), true))
                    <option value="{{ $selectedClassType }}" selected>{{ $selectedClassType }} (tersimpan)</option>
                @endif
            </select>
            <p class="dpe-dashboard-field__hint">Tipe ini membantu membedakan pola belajar dan pengaturan kelas.</p>
            @error('class_type')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="monthly_video_target_override">Target video bulanan <span>(opsional)</span></label>
            <input id="monthly_video_target_override" name="monthly_video_target_override" type="number" value="{{ old('monthly_video_target_override', $program?->monthly_video_target_override) }}" min="0" step="1" placeholder="Contoh: 4">
            <p class="dpe-dashboard-field__hint">Kosongkan jika program mengikuti target default sistem.</p>
            @error('monthly_video_target_override')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <div class="dpe-dashboard-checkbox">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" name="is_active" type="checkbox" value="1" @checked($activeValue)>
                <label for="is_active">Program aktif dan dapat digunakan untuk data operasional baru</label>
            </div>
            @error('is_active')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.programs.show', $program) : route('superadmin.programs.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan program' }}</span>
        </button>
    </div>
</form>
