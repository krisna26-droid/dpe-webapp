@php
    $student = $student ?? null;
    $isEdit = $student !== null;
    $formAction = $isEdit
        ? route('superadmin.students.update', $student)
        : route('superadmin.students.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedUser = old('portal_user_id', $isEdit ? $student->portal_user_id : request('portal_user_id'));
    $beganOn = old('began_on', $student?->began_on?->format('Y-m-d'));
@endphp

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan.</span>
    </div>
@endif

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="dpe-dashboard-form-grid">
        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="portal_user_id">Akun portal siswa</label>
            <select id="portal_user_id" name="portal_user_id" required>
                <option value="">Pilih akun siswa</option>
                @foreach ($studentUsers as $user)
                    <option value="{{ $user->id }}" @selected((string) $selectedUser === (string) $user->id)>
                        {{ $user->full_name ?: $user->username }} ({{ $user->username }})
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Satu akun portal dipasangkan ke satu anak. Pilih akun aktif dengan role Siswa / Parent yang belum memiliki profil.</p>
            @if ($studentUsers->isEmpty() && ! $isEdit)
                <p class="dpe-dashboard-field__error">Belum ada akun student aktif yang belum terhubung ke profil.</p>
            @endif
            @error('portal_user_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
            @if (! $isEdit)
                <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.users.create', ['role_code' => 'student']) }}">
                    <x-dashboard.icon name="plus" />
                    <span>Buat akun siswa</span>
                </a>
            @endif
        </div>

        <div class="dpe-dashboard-field">
            <label for="full_name">Nama lengkap siswa</label>
            <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $student?->full_name) }}" maxlength="255" required>
            @error('full_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="began_on">Tanggal mulai belajar</label>
            <input id="began_on" name="began_on" type="date" value="{{ $beganOn }}" required>
            <p class="dpe-dashboard-field__hint">Tanggal ini menjadi awal riwayat belajar siswa.</p>
            @error('began_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="school_name">Nama sekolah <span>(opsional)</span></label>
            <input id="school_name" name="school_name" type="text" value="{{ old('school_name', $student?->school_name) }}" maxlength="255">
            @error('school_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="grade_name">Kelas / tingkat <span>(opsional)</span></label>
            <input id="grade_name" name="grade_name" type="text" value="{{ old('grade_name', $student?->grade_name) }}" maxlength="255">
            @error('grade_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="photo">Foto siswa <span>(opsional, JPG/PNG maksimal 2 MB)</span></label>
            <input id="photo" name="photo" type="file" accept="image/*">
            <p class="dpe-dashboard-field__hint">Foto akan dipakai sebagai identitas profil saat modul portal siswa dikembangkan.</p>
            @error('photo')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="special_notes_internal">Catatan internal <span>(hanya untuk pengelola)</span></label>
            <textarea id="special_notes_internal" name="special_notes_internal" maxlength="10000">{{ old('special_notes_internal', $student?->special_notes_internal) }}</textarea>
            <p class="dpe-dashboard-field__hint">Gunakan untuk kebutuhan operasional internal. Catatan ini tidak ditampilkan di portal siswa.</p>
            @error('special_notes_internal')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.students.show', $student) : route('superadmin.students.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($studentUsers->isEmpty() && ! $isEdit)>
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan profil siswa' }}</span>
        </button>
    </div>
</form>
