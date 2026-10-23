@php
    $guardian = $guardian ?? null;
    $isEdit = $guardian !== null;
    $students = $students ?? \App\Models\Student::query()
        ->where('status', 'active')
        ->with('portalUser')
        ->orderBy('full_name')
        ->get();
    $formAction = $isEdit
        ? route('superadmin.guardians.update', $guardian)
        : route('superadmin.guardians.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedStudent = old('student_id', $guardian?->student_id ?: request('student_id'));
    $primaryValue = old('is_primary', $guardian?->is_primary ?? false);
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
        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="student_id">Siswa</label>
            <select id="student_id" name="student_id" required>
                <option value="">Pilih siswa</option>
                @foreach ($students as $studentOption)
                    <option value="{{ $studentOption->id }}" @selected((string) $selectedStudent === (string) $studentOption->id)>
                        {{ $studentOption->full_name }}{{ $studentOption->portalUser?->username ? ' · '.$studentOption->portalUser->username : '' }}
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Pilih anak yang menerima komunikasi dan pembagian rapor dari kontak ini.</p>
            @if ($students->isEmpty())<p class="dpe-dashboard-field__error">Belum ada profil siswa aktif.</p>@endif
            @error('student_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="full_name">Nama wali / penerima</label>
            <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $guardian?->full_name) }}" maxlength="255" required>
            @error('full_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="relationship_name">Hubungan dengan siswa <span>(opsional)</span></label>
            <input id="relationship_name" name="relationship_name" type="text" value="{{ old('relationship_name', $guardian?->relationship_name) }}" maxlength="255" placeholder="Contoh: Ayah, Ibu, Kakak">
            @error('relationship_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="whatsapp_number">Nomor WhatsApp</label>
            <input id="whatsapp_number" name="whatsapp_number" type="text" value="{{ old('whatsapp_number', $guardian?->whatsapp_number) }}" maxlength="255" placeholder="Contoh: 08123456789" required>
            <p class="dpe-dashboard-field__hint">Gunakan nomor yang dapat menerima komunikasi dan tautan rapor.</p>
            @error('whatsapp_number')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label>Penerima rapor</label>
            <div class="dpe-dashboard-checkbox">
                <input type="hidden" name="is_primary" value="0">
                <input id="is_primary" name="is_primary" type="checkbox" value="1" @checked(filter_var($primaryValue, FILTER_VALIDATE_BOOLEAN))>
                <label for="is_primary">Tandai sebagai penerima utama</label>
            </div>
            <p class="dpe-dashboard-field__hint">Setiap siswa hanya memiliki satu penerima utama. Saat mengedit kontak lain menjadi utama, penerima utama sebelumnya akan dilepas otomatis.</p>
            @error('is_primary')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.guardians.show', $guardian) : route('superadmin.guardians.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($students->isEmpty() && ! $isEdit)>
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan wali' }}</span>
        </button>
    </div>
</form>
