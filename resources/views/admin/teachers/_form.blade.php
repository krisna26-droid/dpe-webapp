@php
    $teacher = $teacher ?? null;
    $isEdit = $teacher !== null;
    $formAction = $isEdit
        ? route('admin.teachers.update', $teacher)
        : route('admin.teachers.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedUser = old('user_id', $teacher?->user_id ?: request('user_id'));
    $isSuperadmin = auth()->user()?->role_code === 'superadmin';
@endphp

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan.</span>
    </div>
@endif

@if ($users->isEmpty())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--warning" role="alert">
        <x-dashboard.icon name="info" />
        <span>Belum ada akun Guru aktif yang belum memiliki profil.</span>
    </div>
@endif

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="dpe-dashboard-form-grid">
        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="user_id">Akun login guru</label>
            <select id="user_id" name="user_id" required>
                <option value="">Pilih akun Guru</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) $selectedUser === (string) $user->id)>
                        {{ $user->full_name ?: $user->username }} ({{ $user->username }})
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Hanya akun aktif dengan role Guru yang tampil. Satu akun hanya dapat memiliki satu profil guru.</p>
            @error('user_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="whatsapp_number">Nomor WhatsApp</label>
            <input id="whatsapp_number" name="whatsapp_number" type="text" value="{{ old('whatsapp_number', $teacher?->whatsapp_number) }}" maxlength="255" inputmode="tel" autocomplete="tel" placeholder="Contoh: 081234567890" required>
            <p class="dpe-dashboard-field__hint">Nomor ini dipakai untuk kebutuhan komunikasi operasional guru.</p>
            @error('whatsapp_number')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="photo">Foto guru <span>(opsional, maksimal 2 MB)</span></label>
            <input id="photo" name="photo" type="file" accept="image/*">
            @error('photo')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-note">
        <strong>{{ $isEdit ? 'Perubahan profil' : 'Setelah profil tersimpan' }}</strong>
        @if ($isEdit)
            Penugasan cabang dan periode tugas tidak diubah dari formulir ini. Hubungi Super Admin jika penempatannya perlu diperbarui.
        @else
            @if ($isSuperadmin)
                Super Admin akan diarahkan kembali ke form penugasan agar guru dapat dihubungkan dengan cabang dan periode tugasnya.
            @else
                Profil guru akan tampil setelah memiliki penugasan aktif pada cabang yang Anda kelola.
            @endif
        @endif
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('admin.teachers.show', $teacher) : ($isSuperadmin ? route('superadmin.teacher-branch-assignments.index') : route('admin.teachers.index')) }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($users->isEmpty())>
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan profil guru' }}</span>
        </button>
    </div>
</form>
