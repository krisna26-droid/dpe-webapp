@extends('layouts.dashboard')

@section('title', 'Tambah Pengguna Siswa')
@section('heading', 'Pengguna')
@section('description', 'Satu kali simpan untuk membuat akun, profil siswa, dan pendaftaran cabang.')

@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/own-account.css') }}?v={{ filemtime(public_path('assets/dpe/own-account.css')) }}">
@endpush
@push('scripts')
    <script src="{{ asset('assets/dpe/own-account.js') }}?v={{ filemtime(public_path('assets/dpe/own-account.js')) }}" defer></script>
@endpush

@section('content')
    <x-dashboard.form-panel title="Tambah pengguna siswa" description="Akun baru memiliki role Siswa / Parent dan langsung aktif. Isi nama anak sebagai nama lengkap." :close-url="route('admin.users.index')">
        @if ($errors->any())
            <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
                <x-dashboard.icon name="warning" />
                <div><strong>Periksa kembali data berikut.</strong><ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
            </div>
        @endif
        @if ($branches->isEmpty())
            <div class="dpe-dashboard-alert dpe-dashboard-alert--warning" role="alert"><x-dashboard.icon name="info" /><span>Belum ada penugasan cabang aktif. Minta Super Admin menyiapkan penugasan Anda terlebih dahulu.</span></div>
        @endif
        @if ($programs->isEmpty())
            <div class="dpe-dashboard-alert dpe-dashboard-alert--warning" role="alert"><x-dashboard.icon name="info" /><span>Belum ada program belajar aktif. Minta Super Admin menyiapkan program terlebih dahulu.</span></div>
        @endif

        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf
            <div class="dpe-dashboard-form-grid">
                <div class="dpe-dashboard-field">
                    <label for="full_name">Nama lengkap siswa</label>
                    <input id="full_name" name="full_name" type="text" value="{{ old('full_name') }}" maxlength="255" autocomplete="name" required>
                    @error('full_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" value="{{ old('username') }}" maxlength="255" autocomplete="off" autocapitalize="none" spellcheck="false" required aria-describedby="username_hint">
                    <p id="username_hint" class="dpe-dashboard-field__hint">Gunakan huruf, angka, tanda hubung atau garis bawah, tanpa spasi.</p>
                    @error('username')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field">
                    <label for="email">Email <span>(opsional)</span></label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email">
                    @error('email')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field">
                    <label for="account_role">Role pengguna</label>
                    <input id="account_role" type="text" value="Siswa / Parent" readonly>
                    <p class="dpe-dashboard-field__hint">Admin Cabang dapat membuat akun siswa.</p>
                </div>
                <div class="dpe-dashboard-field">
                    <label for="password">Password</label>
                    <div class="dpe-account-password">
                        <input id="password" name="password" type="password" minlength="8" autocomplete="new-password" required aria-describedby="password_hint">
                        <button type="button" class="dpe-account-password-toggle" data-account-toggle="password" data-account-label="password" aria-controls="password" aria-label="Tampilkan password" aria-pressed="false" hidden><x-dpe.icon name="eye" /></button>
                    </div>
                    <p id="password_hint" class="dpe-dashboard-field__hint">Minimal 8 karakter. Isi ulang password jika form gagal disimpan.</p>
                    @error('password')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field">
                    <label for="password_confirmation">Konfirmasi password</label>
                    <div class="dpe-account-password">
                        <input id="password_confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                        <button type="button" class="dpe-account-password-toggle" data-account-toggle="password_confirmation" data-account-label="konfirmasi password" aria-controls="password_confirmation" aria-label="Tampilkan konfirmasi password" aria-pressed="false" hidden><x-dpe.icon name="eye" /></button>
                    </div>
                    @error('password_confirmation')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field">
                    @if ($branches->count() > 1)
                        <label for="branch_id">Cabang yang dikelola</label>
                        <select id="branch_id" name="branch_id" required>
                            <option value="">Pilih cabang Anda</option>
                            @foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') === $branch->id)>{{ $branch->name }}</option>@endforeach
                        </select>
                    @else
                        <label for="account_branch">Cabang otomatis</label>
                        <input id="account_branch" type="text" value="{{ $branches->first()?->name ?? 'Belum ada penugasan aktif' }}" readonly>
                        @if ($branches->isNotEmpty())<input type="hidden" name="branch_id" value="{{ $branches->first()->id }}">@endif
                    @endif
                    <p class="dpe-dashboard-field__hint">Siswa langsung terdaftar di cabang yang Anda kelola.</p>
                    @error('branch_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field">
                    <label for="program_id">Program belajar</label>
                    <select id="program_id" name="program_id" required>
                        <option value="">Pilih program belajar</option>
                        @foreach ($programs as $program)<option value="{{ $program->id }}" @selected(old('program_id') === $program->id)>{{ $program->name }}</option>@endforeach
                    </select>
                    @error('program_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field">
                    <label for="began_on">Tanggal mulai belajar dan pendaftaran</label>
                    <input id="began_on" name="began_on" type="date" value="{{ old('began_on', today()->toDateString()) }}" required aria-describedby="began_on_hint">
                    <p id="began_on_hint" class="dpe-dashboard-field__hint">Pendaftaran cabang dimulai pada tanggal ini. Jika memilih tanggal mendatang, siswa muncul di Data Siswa saat tanggal mulai tiba.</p>
                    @error('began_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field">
                    <label for="school_name">Nama sekolah <span>(opsional)</span></label>
                    <input id="school_name" name="school_name" type="text" value="{{ old('school_name') }}" maxlength="255">
                    @error('school_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field">
                    <label for="grade_name">Kelas / tingkat <span>(opsional)</span></label>
                    <input id="grade_name" name="grade_name" type="text" value="{{ old('grade_name') }}" maxlength="255">
                    @error('grade_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="dpe-dashboard-form-actions">
                <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.users.index') }}">Batal</a>
                <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($branches->isEmpty() || $programs->isEmpty())><x-dashboard.icon name="check" /><span>Simpan pengguna siswa</span></button>
            </div>
        </form>
    </x-dashboard.form-panel>
@endsection
