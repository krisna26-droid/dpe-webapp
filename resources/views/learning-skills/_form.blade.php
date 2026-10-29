@php
    $learningSkill = $learningSkill ?? null;
    $isEdit = $learningSkill !== null;
    $formAction = $isEdit
        ? route('superadmin.learning-skills.update', $learningSkill)
        : route('superadmin.learning-skills.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $activeValue = old('is_active', $isEdit ? (int) $learningSkill->is_active : 1);
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
            <label for="code">Kode skill</label>
            <input id="code" name="code" type="text" value="{{ old('code', $learningSkill?->code) }}" placeholder="Contoh: LISTENING" maxlength="255" required>
            <p class="dpe-dashboard-field__hint">Gunakan kode singkat dan unik untuk kebutuhan internal.</p>
            @error('code')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="name">Nama kompetensi</label>
            <input id="name" name="name" type="text" value="{{ old('name', $learningSkill?->name) }}" placeholder="Contoh: Listening" maxlength="255" required>
            @error('name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="display_order">Urutan tampil</label>
            <input id="display_order" name="display_order" type="number" value="{{ old('display_order', $learningSkill?->display_order ?? 0) }}" min="0" step="1" required>
            <p class="dpe-dashboard-field__hint">Angka lebih kecil tampil lebih dahulu pada daftar kompetensi.</p>
            @error('display_order')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>


    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.learning-skills.show', $learningSkill) : route('superadmin.learning-skills.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan skill' }}</span>
        </button>
    </div>
</form>
