@php
    $managedUser = $managedUser ?? null;
    $isEdit = $managedUser !== null;
    $roleLabels = [
        'admin' => 'Admin Cabang',
        'teacher' => 'Guru',
        'student' => 'Siswa / Parent',
    ];
    if ($managedUser?->role_code === 'superadmin') $roleLabels = ['superadmin' => 'Super Admin'] + $roleLabels;
    $selectedRole = old('role_code', $managedUser?->role_code ?? request('role_code'));
    $activeValue = filter_var(old('is_active', $managedUser?->is_active ?? true), FILTER_VALIDATE_BOOLEAN);
@endphp

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/own-account.css') }}?v={{ filemtime(public_path('assets/dpe/own-account.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/dpe/own-account.js') }}?v={{ filemtime(public_path('assets/dpe/own-account.js')) }}" defer></script>
@endpush

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan.</span>
    </div>
@endif

<form method="POST" action="{{ $isEdit ? route('superadmin.users.update', $managedUser) : route('superadmin.users.store') }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="dpe-dashboard-form-grid">
        <div class="dpe-dashboard-field">
            <label for="full_name">Nama lengkap</label>
            <input id="full_name" name="full_name" type="text" value="{{ old('full_name', $managedUser?->full_name) }}" maxlength="255" autocomplete="name" required aria-invalid="{{ $errors->has('full_name') ? 'true' : 'false' }}">
            @error('full_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" value="{{ old('username', $managedUser?->username) }}" maxlength="255" autocomplete="off" autocapitalize="none" spellcheck="false" required aria-describedby="username_hint" aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}">
            <p id="username_hint" class="dpe-dashboard-field__hint">Gunakan username unik tanpa spasi: huruf, angka, tanda hubung, atau garis bawah.</p>
            @error('username')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="email">Email <span>(opsional)</span></label>
            <input id="email" name="email" type="email" value="{{ old('email', $managedUser?->email) }}" maxlength="255" autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
            <p class="dpe-dashboard-field__hint">Jika diisi, gunakan email yang belum dipakai akun lain.</p>
            @error('email')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="role_code">Role pengguna</label>
            <select id="role_code" name="role_code" required aria-describedby="role_hint" aria-invalid="{{ $errors->has('role_code') ? 'true' : 'false' }}">
                <option value="">Pilih role pengguna</option>
                @foreach ($roleLabels as $roleCode => $roleLabel)
                    <option value="{{ $roleCode }}" @selected($selectedRole === $roleCode)>{{ $roleLabel }}</option>
                @endforeach
            </select>
            <p id="role_hint" class="dpe-dashboard-field__hint">Untuk membuat siswa, pilih Siswa / Parent, lalu lengkapi profil siswa setelah akun disimpan.</p>
            @error('role_code')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="password">Password</label>
            <div class="dpe-account-password">
                <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" @required(!$isEdit) aria-describedby="password_hint" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                <button type="button" class="dpe-account-password-toggle" data-account-toggle="password" data-account-label="password" aria-controls="password" aria-label="Tampilkan password" aria-pressed="false" hidden><x-dpe.icon name="eye" /></button>
            </div>
            <p id="password_hint" class="dpe-dashboard-field__hint">{{ $isEdit ? 'Kosongkan untuk mempertahankan password saat ini. Jika diganti, gunakan minimal 8 karakter.' : 'Minimal 8 karakter. Password perlu diisi ulang jika form gagal disimpan.' }}</p>
            @error('password')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="password_confirmation">Konfirmasi password</label>
            <div class="dpe-account-password">
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" @required(!$isEdit) aria-invalid="{{ $errors->has('password') || $errors->has('password_confirmation') ? 'true' : 'false' }}">
                <button type="button" class="dpe-account-password-toggle" data-account-toggle="password_confirmation" data-account-label="konfirmasi password" aria-controls="password_confirmation" aria-label="Tampilkan konfirmasi password" aria-pressed="false" hidden><x-dpe.icon name="eye" /></button>
            </div>
            <p class="dpe-dashboard-field__hint">Ketik kembali password yang sama.</p>
            @error('password_confirmation')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <div class="dpe-dashboard-checkbox">
                <input name="is_active" type="hidden" value="0">
                <input id="is_active" name="is_active" type="checkbox" value="1" @checked($activeValue)>
                <label for="is_active">Aktifkan akun pengguna</label>
            </div>
            <p class="dpe-dashboard-field__hint">Akun aktif dapat masuk ke portal. Akun siswa harus aktif agar bisa dihubungkan dengan profil anak.</p>
            @error('is_active')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.users.show', $managedUser) : route('superadmin.users.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan pengguna' }}</span>
        </button>
    </div>
</form>
