<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Program, cabang, dan informasi belajar {{ $site['identity']['title'] }}.">
    <meta name="theme-color" content="#2563EB">
    <title>@yield('title', 'Beranda') — {{ $site['identity']['title'] }}</title>
    <x-dpe.assets />
</head>
<body class="dpe-site">
    <a class="skip-link" href="#main-content">Lewati ke konten</a>
    @isset($websitePreview)
        <aside class="website-preview-banner" role="status"><div><strong>Pratinjau draft · {{ $websitePreview['label'] }}</strong><span>Versi tersimpan {{ $websitePreview['savedAt'] }}. Perubahan bagian ini belum ditayangkan.</span></div><a class="button button-outline" href="{{ $websitePreview['backUrl'] }}">Kembali ke editor</a></aside>
    @endisset

    <header class="site-header">
        <div class="container header-inner">
            <x-dpe.brand :identity="$site['identity']" :home-url="isset($websitePreview) ? $websitePreview['homeUrl'].'#beranda' : null" />
            <button class="menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="site-navigation" aria-label="Buka menu navigasi" hidden>
                <x-dpe.icon name="menu" />
            </button>
            <nav id="site-navigation" class="site-nav" aria-label="Navigasi utama">
                @foreach ([
                    'beranda' => 'Beranda',
                    'tentang' => 'Tentang',
                    'program' => 'Program',
                    'cabang' => 'Cabang',
                    'kontak' => 'Kontak',
                ] as $anchor => $label)
                    @php
                        $routeName = match ($anchor) {
                            'tentang' => 'public.about',
                            'program' => 'public.programs',
                            'cabang' => 'public.branches',
                            'kontak' => 'public.contact',
                            default => 'home',
                        };
                        $active = request()->routeIs($routeName) || ($anchor === 'program' && request()->routeIs('public.program'));
                    @endphp
                    <a href="{{ isset($websitePreview) ? $websitePreview['homeUrl'].'#'.$anchor : route('home').'#'.$anchor }}" class="nav-link{{ $active ? ' is-active' : '' }}" data-section="{{ $anchor }}" @if ($active) aria-current="page" @endif>{{ $label }}</a>
                @endforeach
                @auth
                    @php($dashboard = in_array(auth()->user()->role_code, ['superadmin', 'admin', 'teacher', 'student'], true) ? auth()->user()->role_code.'.dashboard' : null)
                    @if ($dashboard)
                        <a class="button button-primary nav-login" href="{{ route($dashboard) }}">Dashboard <x-dpe.icon name="arrow" /></a>
                    @endif
                @else
                    <a class="button button-outline nav-login" href="{{ route('login') }}">Login <x-dpe.icon name="arrow" /></a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">@yield('content')</main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div class="footer-brand">
                <x-dpe.brand :identity="$site['identity']" :home-url="isset($websitePreview) ? $websitePreview['homeUrl'].'#beranda' : null" />
                <p>{{ $site['identity']['description'] ?: 'Belajar sesuai kebutuhan dan tujuan.' }}</p>
            </div>
            <div>
                <h2>Jelajahi DPE</h2>
                <nav class="footer-links" aria-label="Navigasi footer">
                    <a href="{{ (isset($websitePreview) ? $websitePreview['homeUrl'] : route('home')) }}#tentang">Tentang</a>
                    <a href="{{ (isset($websitePreview) ? $websitePreview['homeUrl'] : route('home')) }}#program">Program</a>
                    <a href="{{ (isset($websitePreview) ? $websitePreview['homeUrl'] : route('home')) }}#cabang">Cabang</a>
                    <a href="{{ (isset($websitePreview) ? $websitePreview['homeUrl'] : route('home')) }}#kontak">Kontak</a>
                </nav>
            </div>
            <div class="footer-aside">
                <h2>Mari terhubung</h2>
                <div class="footer-links">
                    <a href="{{ (isset($websitePreview) ? $websitePreview['homeUrl'] : route('home')) }}#kontak">Konsultasi belajar <x-dpe.icon name="arrow" /></a>
                    @if ($site['contact']['email'])<a href="mailto:{{ $site['contact']['email'] }}">{{ $site['contact']['email'] }}</a>@endif
                    @if ($site['contact']['service_hours'])<span>{{ $site['contact']['service_hours'] }}</span>@endif
                    @foreach ($site['contact']['socials'] as $social)<a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer">{{ $social['name'] }}</a>@endforeach
                    @if ($site['is_sample'])<span class="footer-prototype-note">Konten &amp; foto contoh untuk prototype <span class="sr-only">Informasi contoh.</span></span>@endif
                </div>
            </div>
        </div>
        <div class="container footer-bottom">
            <span>© {{ date('Y') }} {{ $site['identity']['title'] }}.</span>
            @if ($site['identity']['footer_text'] !== '')<span>{{ $site['identity']['footer_text'] }}</span>@endif
        </div>
    </footer>
</body>
</html>
