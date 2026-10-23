@php
    $student = $student ?? null;
    $isEdit = $student !== null;
    $formAction = $isEdit
        ? route('admin.students.update', $student)
        : route('admin.students.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedUser = old('portal_user_id', $student?->portal_user_id ?: request('portal_user_id'));
    $beganOn = old('began_on', $student?->began_on?->format('Y-m-d') ?: now()->format('Y-m-d'));
@endphp

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan.</span>
    </div>
@endif

@if ($studentUsers->isEmpty())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--warning" role="alert">
        <x-dashboard.icon name="info" />
        <span>Belum ada akun siswa aktif yang belum memiliki profil. Jika akun sudah dipakai untuk profil siswa, jangan membuat profil kedua; atur pendaftaran cabang siswa tersebut dari menu Siswa &amp; Pendaftaran Super Admin.</span>
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
            <p class="dpe-dashboard-field__hint">Satu akun Student hanya dapat terhubung ke satu profil siswa.</p>
            @error('portal_user_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="full_name">Nama lengkap</label>
            <input id="full_name" name="full_name" type="text" maxlength="255" value="{{ old('full_name', $student?->full_name) }}" required>
            @error('full_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="school_name">Nama sekolah <span>(opsional)</span></label>
            <input id="school_name" name="school_name" type="text" maxlength="255" value="{{ old('school_name', $student?->school_name) }}">
            @error('school_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="grade_name">Kelas / tingkat <span>(opsional)</span></label>
            <input id="grade_name" name="grade_name" type="text" maxlength="255" value="{{ old('grade_name', $student?->grade_name) }}">
            @error('grade_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="began_on">Tanggal mulai belajar</label>
            <input id="began_on" name="began_on" type="date" value="{{ $beganOn }}" required>
            @error('began_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="photo">Foto siswa <span>(opsional, maksimal 2 MB)</span></label>
            <input id="photo" name="photo" type="file" accept="image/*">
            @error('photo')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="special_notes_internal">Catatan internal <span>(opsional)</span></label>
            <textarea id="special_notes_internal" name="special_notes_internal" maxlength="10000" placeholder="Catatan yang hanya dipakai untuk kebutuhan operasional">{{ old('special_notes_internal', $student?->special_notes_internal) }}</textarea>
            <p class="dpe-dashboard-field__hint">Catatan ini tidak ditampilkan pada portal siswa.</p>
            @error('special_notes_internal')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('admin.students.show', $student) : route('admin.students.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($studentUsers->isEmpty())>
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan profil siswa' }}</span>
        </button>
    </div>
</form>
