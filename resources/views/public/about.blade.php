@extends('layouts.public')
@section('title', 'Tentang kami')
@section('content')
<section class="page-heading container"><span class="eyebrow">Tentang DPE</span><h1>Kenal lebih dekat.<br><span class="accent">Belajar lebih percaya diri.</span></h1><p>Mengenal Dharma Private English dan perjalanan belajar yang ingin kamu mulai.</p></section>
<section class="container about-grid">
    <figure class="about-photo"><img src="{{ $site['about']['intro']['image'] ?: asset('assets/dpe/english-lesson.png') }}" alt="Suasana pendampingan belajar bahasa Inggris" width="1536" height="1024">@unless ($site['about']['intro']['image'])<figcaption class="illustration-label">Foto ilustrasi</figcaption>@endunless</figure>
    <div class="about-copy"><span class="eyebrow">{{ $site['about']['intro']['eyebrow'] ?: 'Cerita kami' }}</span>
        @if ($site['about']['intro']['title'] || $site['about']['intro']['description'])<h2>{{ $site['about']['intro']['title'] }}</h2><p class="prose">{{ $site['about']['intro']['description'] }}</p>@else <h2>Profil lembaga sedang disiapkan.</h2><p>Informasi tentang DPE akan ditampilkan setelah siap. Kamu dapat melihat program dan halaman kontak terlebih dahulu.</p>@endif
        <a class="text-link" href="{{ route('public.programs') }}">Kenali pilihan program <x-dpe.icon name="arrow" /></a>
    </div>
</section>
@if ($site['about']['vision']['title'] || $site['about']['mission']['title'])<section class="container vision-grid section-block">@foreach (['vision' => 'target', 'mission' => 'spark'] as $key => $icon)@if ($site['about'][$key]['title'] || $site['about'][$key]['description'])<article class="content-card"><span class="icon-tile"><x-dpe.icon :name="$icon" /></span><h2>{{ $site['about'][$key]['title'] ?: ($key === 'vision' ? 'Visi' : 'Misi') }}</h2><p class="prose">{{ $site['about'][$key]['description'] }}</p></article>@endif @endforeach</section>@endif
@if ($site['about']['approaches'])<section class="soft-section"><div class="container"><div class="section-heading centered"><div><span class="eyebrow">Perjalanan belajar</span><h2>Mulai. Berlatih. Bertumbuh.</h2></div></div><div class="approach-grid">@foreach ($site['about']['approaches'] as $approach)<article><span class="step-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $approach['title'] }}</h3><p>{{ $approach['description'] }}</p></article>@endforeach</div></div></section>@endif
<x-dpe.cta />
@endsection
