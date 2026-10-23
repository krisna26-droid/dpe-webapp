@extends('layouts.public')
@section('title', 'Beranda')
@section('content')
@php
    $isSample = $site['is_sample'];
    $heroImage = $site['hero']['image'] ?: asset('assets/dpe/english-lesson.png');
    $heroTitle = $isSample ? 'Bahasa Inggris lebih dekat dengan tujuan belajarmu.' : ($site['hero']['title'] ?: 'Bahasa Inggris untuk langkah besarmu.');
    $heroDescription = $site['hero']['description'] ?: 'Program belajar untuk membangun kemampuan dan rasa percaya diri, dengan dukungan tutor di setiap langkah.';
    $aboutIntro = $site['about']['intro'];
    $aboutTitle = $aboutIntro['title'] ?: 'Pendampingan yang dekat dengan tujuan belajar.';
    $aboutDescription = $aboutIntro['description'] ?: 'Dharma Private English membantu siswa belajar bertahap dengan materi, latihan, jadwal, dan catatan perkembangan yang lebih jelas.';
    $benefits = $site['benefits'] ?: ($isSample ? [
        ['title' => 'Materi terarah', 'description' => 'Materi disusun untuk membantu siswa belajar selangkah demi selangkah.'],
        ['title' => 'Progres terlihat', 'description' => 'Catatan belajar membantu siswa dan orang tua mengikuti perkembangan.'],
        ['title' => 'Jadwal lebih jelas', 'description' => 'Informasi jadwal membantu menyiapkan sesi belajar berikutnya.'],
    ] : []);
    $sampleBranchAddresses = [
        'GatSu' => 'Jl. Gatot Subroto Timur No. 101, Denpasar',
        'Batubulan' => 'Jl. Raya Batubulan No. 12, Gianyar',
        'Renon' => 'Jl. Tukad Badung No. 18, Denpasar',
        'Dalung' => 'Jl. Raya Dalung No. 24, Badung',
        'Sukawati' => 'Jl. Raya Sukawati No. 30, Gianyar',
    ];
    $sampleContact = [
        'whatsapp' => '08xx-0000-0000',
        'email' => 'info@dpe.example',
        'service_hours' => 'Senin–Sabtu • 09.00–18.00 WITA',
    ];
    $contactNumber = $site['contact']['whatsapp'] ?: ($isSample ? $sampleContact['whatsapp'] : '');
    $contactEmail = $site['contact']['email'] ?: ($isSample ? $sampleContact['email'] : '');
    $serviceHours = $site['contact']['service_hours'] ?: ($isSample ? $sampleContact['service_hours'] : 'Informasi jam pelayanan belum tersedia.');
@endphp

<section id="beranda" class="onepage-hero onepage-anchor" aria-labelledby="hero-title">
    <div class="onepage-hero-media" aria-hidden="true"><img src="{{ $heroImage }}" alt="" width="1536" height="1024" fetchpriority="high"></div>
    <div class="onepage-hero-scrim" aria-hidden="true"></div>
    <div class="container onepage-hero-content">
        <span class="onepage-pill">{{ $site['hero']['eyebrow'] ?: 'Belajar bersama DPE' }}</span>
        <h1 id="hero-title">{{ $heroTitle }}</h1>
        <p>{{ $heroDescription }}</p>
        <div class="button-row onepage-hero-actions">
            @if ($site['hero']['primary_label'] && $site['hero']['primary_url'])<a class="button button-primary" href="{{ $site['hero']['primary_url'] }}">{{ $site['hero']['primary_label'] }} <x-dpe.icon name="arrow" /></a>@endif
            @if ($site['hero']['secondary_label'] && $site['hero']['secondary_url'])<a class="button button-white" href="{{ $site['hero']['secondary_url'] }}"><x-dpe.icon name="message" /> {{ $site['hero']['secondary_label'] }}</a>@endif
        </div>
        <p class="onepage-hero-meta">Regular <span>•</span> General Private <span>•</span> ESP Private</p>
    </div>
    @if ($site['hero']['caption'] || ! $site['hero']['image'])<span class="onepage-hero-caption">{{ $site['hero']['caption'] ?: 'Foto contoh kegiatan belajar' }}</span>@endif
</section>

<div class="onepage-content">
    @if ($site['intro']['title'] || $site['intro']['description'])
        <section class="container onepage-managed-intro"><div>@if ($site['intro']['eyebrow'])<span class="eyebrow">{{ $site['intro']['eyebrow'] }}</span>@endif<h2>{{ $site['intro']['title'] }}</h2><p class="prose">{{ $site['intro']['description'] }}</p></div>@if ($site['intro']['image'])<img src="{{ $site['intro']['image'] }}" alt="{{ $site['intro']['title'] }}" width="600" height="360" loading="lazy">@endif</section>
    @endif
    <section class="container onepage-features" aria-label="Keunggulan layanan">
        @foreach ($benefits as $benefit)
            <article class="onepage-feature-item">
                @if ($benefit['image'] ?? null)<img class="onepage-feature-photo" src="{{ $benefit['image'] }}" width="52" height="52" alt="" loading="lazy">@else<span class="icon-tile onepage-feature-icon"><x-dpe.icon :name="['book', 'check', 'clock'][$loop->index % 3]" /></span>@endif
                <div><h2>{{ $benefit['title'] }}</h2><p>{{ $benefit['description'] }}</p></div>
            </article>
        @endforeach
    </section>

    <section id="tentang" class="onepage-section onepage-anchor" aria-labelledby="about-title">
        <div class="container">
            <div class="onepage-section-heading">
                <span class="eyebrow">{{ $aboutIntro['eyebrow'] ?: 'Tentang DPE' }}</span>
                <h2 id="about-title">{{ ($aboutIntro['heading'] ?? '') ?: 'Belajar dengan arah yang jelas.' }}</h2>
                <p>{{ ($aboutIntro['heading_description'] ?? '') ?: 'Kenali Dharma Private English dan cara kami mendampingi proses belajarmu.' }}</p>
            </div>
            <div class="onepage-about-intro">
                <figure class="onepage-photo">
                    <img src="{{ $aboutIntro['image'] ?: $heroImage }}" alt="Suasana pendampingan belajar bahasa Inggris" width="1536" height="1024" loading="lazy">
                    @unless ($aboutIntro['image'] || $site['hero']['image'])<figcaption>Foto contoh kegiatan belajar</figcaption>@endunless
                </figure>
                <div class="onepage-about-copy">
                    <h3>{{ $aboutTitle }}</h3>
                    <p class="prose">{{ $aboutDescription }}</p>
                    @if ($aboutIntro['detail'] ?? '')<p class="prose">{{ $aboutIntro['detail'] }}</p>@endif
                    <div class="onepage-vision-grid">
                        @foreach (['vision' => ['label' => 'Visi', 'icon' => 'target'], 'mission' => ['label' => 'Misi', 'icon' => 'check']] as $key => $item)
                            @php($content = $site['about'][$key])
                            <article class="onepage-mini-card"><span class="icon-tile"><x-dpe.icon :name="$item['icon']" /></span><div><h4>{{ $content['title'] ?: $item['label'] }}</h4><p class="prose">{{ $content['description'] ?: ($key === 'vision' ? 'Mendampingi siswa agar lebih percaya diri menggunakan Bahasa Inggris.' : 'Pembelajaran sesuai kebutuhan, latihan yang konsisten, dan komunikasi tentang progres.') }}</p></div></article>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="onepage-approach">
                <h3>Cara kami mendampingi proses belajarmu</h3>
                <div class="onepage-approach-grid">
                    @foreach (($site['about']['approaches'] ?: ($isSample ? [
                        ['title' => 'Materi terarah', 'description' => 'Materi disusun untuk membantu siswa belajar selangkah demi selangkah.'],
                        ['title' => 'Progres terlihat', 'description' => 'Catatan belajar membantu siswa dan orang tua mengikuti perkembangan.'],
                        ['title' => 'Jadwal lebih jelas', 'description' => 'Informasi jadwal membantu menyiapkan sesi belajar berikutnya.'],
                    ] : [])) as $approach)
                        <article class="onepage-approach-card">@if ($approach['image'] ?? null)<img class="onepage-feature-photo" src="{{ $approach['image'] }}" width="52" height="52" alt="" loading="lazy">@else<span class="icon-tile"><x-dpe.icon :name="['book', 'check', 'clock'][$loop->index % 3]" /></span>@endif<div><h4>{{ $approach['title'] }}</h4><p>{{ $approach['description'] }}</p></div></article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="program" class="onepage-section onepage-section-alt onepage-anchor" aria-labelledby="program-title">
        <div class="container">
            <div class="onepage-section-heading">
                <span class="eyebrow">Program belajar</span>
                <h2 id="program-title">Pilihan belajar untuk kebutuhan berbeda.</h2>
                <p>Temukan gambaran program, lalu konsultasikan kebutuhan dan jadwal bersama DPE.</p>
            </div>
            @if ($site['programs'])
                <div class="onepage-program-grid">
                    @foreach ($site['programs'] as $program)
                        <article class="onepage-program-card">
                            @if ($program['image'])<img class="onepage-catalog-image" src="{{ $program['image'] }}" width="600" height="360" alt="{{ $program['name'] }}" loading="lazy">@endif
                            <div class="onepage-card-top"><span class="icon-tile"><x-dpe.icon :name="['book', 'users', 'spark'][$loop->index % 3]" /></span><span class="onepage-card-label">{{ $program['type_label'] }}</span></div>
                            <h3>{{ $program['name'] }}</h3>
                            <p>{{ $program['description'] ?: 'Pendampingan belajar yang disesuaikan dengan kebutuhan siswa.' }}</p>
                            <div class="onepage-card-divider"></div>
                            <h4>Cocok untuk</h4>
                            <p>{{ $program['audience'] ?: 'Siswa dengan tujuan belajar bahasa Inggris yang ingin berkembang secara bertahap.' }}</p>
                            @if ($program['features'])
                                <ul class="check-list">@foreach (array_slice($program['features'], 0, 3) as $feature)<li><x-dpe.icon name="check" /><span>{{ $feature }}</span></li>@endforeach</ul>
                            @endif
                            <a class="button button-primary onepage-card-cta" href="#kontak">Konsultasikan program <x-dpe.icon name="message" /></a>
                        </article>
                    @endforeach
                </div>
            @else
                <x-dpe.empty title="Informasi program belum tersedia" />
            @endif
            <div class="onepage-steps">
                <h3>Mulai dari kebutuhan belajarmu.</h3>
                <div class="onepage-step-grid">
                    @foreach ([['title' => 'Kenali pilihan program', 'description' => 'Lihat gambaran belajar yang sesuai.'], ['title' => 'Konsultasi dengan DPE', 'description' => 'Ceritakan kebutuhan dan tujuanmu.'], ['title' => 'Tentukan jadwal belajar', 'description' => 'Diskusikan cabang dan jadwal.']] as $step)
                        <article><span class="onepage-step-number">{{ $loop->iteration }}</span><div><h4>{{ $step['title'] }}</h4><p>{{ $step['description'] }}</p></div></article>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section id="cabang" class="onepage-section onepage-anchor" aria-labelledby="branch-title">
        <div class="container">
            <div class="onepage-section-heading">
                <span class="eyebrow">Cabang DPE</span>
                <h2 id="branch-title">Temukan cabang yang dekat denganmu.</h2>
                <p>Kenali informasi lokasi dan kontak untuk menyiapkan kunjungan atau konsultasi.</p>
            </div>
            @if ($isSample)<div class="onepage-branch-notice"><x-dpe.icon name="pin" /><span>Alamat, jam layanan, dan kontak pada prototype ini adalah contoh. Informasi final dikonfirmasi bersama tim DPE.</span></div>@endif
            @if ($site['branches'])
                <div class="onepage-branch-grid">
                    @foreach ($site['branches'] as $branch)
                        @php($branchAddress = $branch['address'] ?: ($isSample ? ($sampleBranchAddresses[$branch['name']] ?? 'Alamat cabang dikonfirmasi bersama DPE.') : 'Alamat belum tersedia'))
                        <article class="onepage-branch-card">
                            @if ($branch['image'])<img class="onepage-catalog-image" src="{{ $branch['image'] }}" width="600" height="360" alt="{{ $branch['name'] }}" loading="lazy">@endif
                            <div class="onepage-card-top"><span class="icon-tile"><x-dpe.icon name="pin" /></span><span class="onepage-card-label">Cabang DPE</span></div>
                            <h3>DPE {{ $branch['name'] }}</h3>
                            @if ($branch['description'])<p class="prose">{{ $branch['description'] }}</p>@endif
                            <p class="onepage-branch-meta"><x-dpe.icon name="pin" />{{ $branchAddress }}</p>
                            <p class="onepage-branch-meta"><x-dpe.icon name="clock" />{{ $branch['service_hours'] ?: ($isSample ? $sampleContact['service_hours'] : 'Jam layanan belum tersedia') }}</p>
                            <div class="onepage-branch-actions">
                                @if ($branch['map_url'])<a class="button button-outline" href="{{ $branch['map_url'] }}" target="_blank" rel="noopener noreferrer"><x-dpe.icon name="pin" /> Lihat peta</a>@else<a class="button button-outline" href="#kontak"><x-dpe.icon name="pin" /> Lihat info</a>@endif
                                <a class="button button-primary" href="{{ $branch['whatsapp_url'] ?: '#kontak' }}" @if ($branch['whatsapp_url']) target="_blank" rel="noopener noreferrer" @endif><x-dpe.icon name="message" /> Hubungi</a>
                            </div>
                        </article>
                    @endforeach
                    <article class="onepage-branch-cta"><x-dpe.icon name="message" /><h3>Belum yakin memilih cabang?</h3><p>Tim DPE membantu mencarikan informasi yang sesuai.</p><a class="button button-white" href="#kontak">Hubungi DPE <x-dpe.icon name="arrow" /></a></article>
                </div>
            @else
                <x-dpe.empty title="Informasi cabang belum tersedia" icon="pin" />
            @endif
        </div>
    </section>

    <section id="kontak" class="onepage-section onepage-section-alt onepage-anchor" aria-labelledby="contact-title">
        <div class="container">
            <div class="onepage-section-heading">
                <span class="eyebrow">Kontak DPE</span>
                <h2 id="contact-title">Mari bicarakan kebutuhan belajarmu.</h2>
                <p>{{ $site['contact']['description'] ?: 'Hubungi tim DPE untuk informasi program, jadwal, dan cabang yang sesuai.' }}</p>
            </div>
            <div class="onepage-contact-layout">
                <div class="onepage-contact-primary">
                    <x-dpe.icon name="message" />
                    <h3>{{ $site['contact']['title'] ?: 'Satu percakapan untuk langkah awalmu.' }}</h3>
                    <p>Ceritakan kebutuhan dan tujuan belajarmu. Tim DPE membantu menjelaskan pilihan program, cabang, dan jadwal.</p>
                    @if ($site['contact']['whatsapp_url'])<a class="button button-white" href="{{ $site['contact']['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer"><x-dpe.icon name="message" /> Chat melalui WhatsApp</a>@else<a class="button button-white" href="#kontak"><x-dpe.icon name="message" /> Chat melalui WhatsApp</a>@endif
                    @if ($contactNumber)<span class="onepage-contact-number">{{ $isSample ? 'Kontak contoh • ' : '' }}{{ $contactNumber }}</span>@endif
                </div>
                <div class="onepage-contact-cards">
                    <article><span class="icon-tile"><x-dpe.icon name="phone" /></span><div><h3>WhatsApp DPE</h3><strong>{{ $contactNumber ?: 'Nomor WhatsApp belum tersedia' }}</strong><p>Informasi program dan konsultasi awal.</p></div></article>
                    <article><span class="icon-tile"><x-dpe.icon name="mail" /></span><div><h3>Email</h3>@if ($contactEmail)<strong>{{ $contactEmail }}</strong>@else<strong>Email belum tersedia</strong>@endif<p>Pertanyaan dan informasi umum.</p></div></article>
                    <article><span class="icon-tile"><x-dpe.icon name="clock" /></span><div><h3>Jam layanan</h3><strong>{{ $serviceHours }}</strong><p>Jadwal kelas dikonfirmasi bersama cabang.</p></div></article>
                    @if ($site['contact']['phone'])<article><span class="icon-tile"><x-dpe.icon name="phone" /></span><div><h3>Telepon</h3><strong>{{ $site['contact']['phone'] }}</strong></div></article>@endif
                </div>
            </div>
            <div class="onepage-contact-bottom">
                <div><h3>Agar konsultasi lebih mudah</h3><ul class="check-list"><li><x-dpe.icon name="check" /> Sampaikan kebutuhan atau tujuan belajar.</li><li><x-dpe.icon name="check" /> Sebutkan pilihan program dan cabang.</li><li><x-dpe.icon name="check" /> Diskusikan jadwal yang memungkinkan.</li></ul></div>
                <div class="onepage-account-card"><h3>Sudah terdaftar di DPE?</h3><p>Masuk untuk melihat jadwal dan informasi pembelajaran.</p><a class="button button-outline" href="{{ route('login') }}">Login ke akun <x-dpe.icon name="arrow" /></a></div>
            </div>
        </div>
    </section>
</div>
@endsection
