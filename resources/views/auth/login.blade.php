@inject('website', 'App\Services\PublicWebsiteService')
@php
    $site = $website->data();
    $loginCssVersion = filemtime(public_path('assets/dpe/login.css'));
    $siteJsVersion = filemtime(public_path('assets/dpe/site.js'));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#2563EB">
    <title>Login — {{ $site['identity']['title'] }}</title>
    <link rel="stylesheet" href="{{ asset('assets/dpe/login.css') }}?v={{ $loginCssVersion }}">
    <x-dpe.branding-assets />
    <script src="{{ asset('assets/dpe/site.js') }}?v={{ $siteJsVersion }}" defer></script>
</head>
<body class="dpe-login-page">
    <a class="dpe-auth-skip" href="#login-form">Lewati ke formulir login</a>
    <main class="dpe-auth-layout">
        <section class="dpe-auth-story" aria-label="Portal belajar Dharma Private English">
            <div class="dpe-auth-orb dpe-auth-orb--top" aria-hidden="true"></div>
            <div class="dpe-auth-orb dpe-auth-orb--left" aria-hidden="true"></div>
            <div class="dpe-auth-orb dpe-auth-orb--bottom" aria-hidden="true"></div>
            <x-dpe.brand :identity="$site['identity']" class="brand--login dpe-auth-brand" />
            <div class="dpe-auth-story-copy">
                <span class="dpe-auth-badge dpe-auth-badge--story">Portal belajar DPE</span>
                <h1>Selamat datang<br>kembali.</h1>
                <p class="dpe-auth-description">Satu tempat untuk mengikuti perjalanan belajar dan perkembangan siswa.</p>
                <div class="dpe-auth-benefits">
                    <div class="dpe-auth-benefit">
                        <span class="dpe-auth-icon-circle"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M8 3v5m8-5v5M4 11h16M8 15h2m4 0h2"/></svg></span>
                        <p><strong>Jadwal lebih jelas</strong><span>Lihat kegiatan belajar sesuai akun.</span></p>
                    </div>
                    <div class="dpe-auth-benefit">
                        <span class="dpe-auth-icon-circle"><x-dpe.icon name="book" /></span>
                        <p><strong>Perkembangan belajar</strong><span>Pantau catatan, video, dan rapor.</span></p>
                    </div>
                    <div class="dpe-auth-benefit">
                        <span class="dpe-auth-icon-circle"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m12 3 8 4v5c0 5-8 9-8 9s-8-4-8-9V7l8-4Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg></span>
                        <p><strong>Akses sesuai peran</strong><span>Data yang tampil mengikuti akun Anda.</span></p>
                    </div>
                </div>
            </div>
            <p class="dpe-auth-story-footer">{{ $site['identity']['title'] }} <span>•</span> Portal belajar</p>
        </section>
        <section class="dpe-auth-panel" aria-labelledby="login-heading">
            <a class="dpe-auth-back" href="{{ route('home') }}#beranda"><x-dpe.icon name="back" /> Kembali ke beranda</a>
            <div class="dpe-auth-card">
                <a class="dpe-auth-mobile-brand" href="{{ route('home') }}#beranda" aria-label="{{ $site['identity']['title'] }} — Beranda">
                    <x-dpe.logo :src="$site['identity']['image'] ?? null" />
                </a>
                <span class="dpe-auth-badge">Akses portal</span>
                <h2 id="login-heading">Masuk ke akun Anda</h2>
                <p class="dpe-auth-intro">Gunakan akun yang diberikan oleh DPE.</p>
                @if (session('account_success'))
                    <div class="dpe-auth-alert dpe-auth-alert--success" role="status"><p>{{ session('account_success') }}</p></div>
                @endif
                @if ($errors->any())
                    <div class="dpe-auth-alert" id="login-errors" role="alert">
                        <strong>Belum berhasil masuk</strong>
                        @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif
                <form id="login-form" method="POST" action="{{ route('login.process') }}" data-login-form>
                    @csrf
                    <div class="dpe-auth-field">
                        <label for="username">Nama pengguna</label>
                        <div class="dpe-auth-input-wrap">
                            <svg class="icon dpe-auth-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="7" r="4"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/></svg>
                            <input type="text" id="username" name="username" value="{{ old('username') }}" placeholder="Masukkan nama pengguna" autocomplete="username" autocapitalize="none" spellcheck="false" required @if ($errors->has('username')) aria-invalid="true" aria-describedby="login-errors" @endif>
                        </div>
                    </div>
                    <div class="dpe-auth-field">
                        <label for="password">Kata sandi</label>
                        <div class="dpe-auth-input-wrap dpe-auth-input-wrap--password">
                            <svg class="icon dpe-auth-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="5" y="10" width="14" height="11" rx="1"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            <input type="password" id="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required @if ($errors->has('password')) aria-invalid="true" aria-describedby="login-errors" @endif>
                            <button class="dpe-auth-password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Tampilkan password" aria-pressed="false" hidden><x-dpe.icon name="eye" /></button>
                        </div>
                    </div>
                    <p class="dpe-auth-forgot">Lupa kata sandi? <a href="{{ route('home') }}#kontak">Hubungi Admin cabang Anda.</a></p>
                    <button class="dpe-auth-submit" type="submit"><span data-submit-label>Masuk ke Portal</span><x-dpe.icon name="arrow" /></button>
                </form>
                <div class="dpe-auth-security">
                    <span class="dpe-auth-icon-circle"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m12 3 8 4v5c0 5-8 9-8 9s-8-4-8-9V7l8-4Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg></span>
                    <p><strong>Satu akun siswa untuk satu anak.</strong><span>Orang tua/wali dapat memakai akun anak tersebut.</span></p>
                </div>
            </div>
            <p class="dpe-auth-panel-footer">Butuh bantuan akses? <a href="{{ route('home') }}#kontak">Hubungi Admin cabang DPE.</a></p>
        </section>
    </main>
</body>
</html>
