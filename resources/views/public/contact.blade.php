@extends('layouts.public')
@section('title', 'Kontak & konsultasi')
@section('content')
<section class="page-heading container"><span class="eyebrow">Kontak & konsultasi</span><h1>{{ $site['contact']['title'] }}</h1><p class="prose">{{ $site['contact']['description'] ?: 'Punya pertanyaan tentang program atau pendaftaran? Temukan informasi untuk menghubungi DPE di sini.' }}</p></section>
<section class="container contact-grid catalog-section"><div class="contact-primary"><span class="icon-tile"><x-dpe.icon name="message" /></span><h2>Konsultasi melalui WhatsApp</h2><p>Diskusikan kebutuhan belajar dan pilihan program yang ingin kamu kenali.</p>
    @if ($site['contact']['whatsapp_url'])<a class="button button-primary" href="{{ $site['contact']['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer"><x-dpe.icon name="message" /> Chat melalui WhatsApp <x-dpe.icon name="external" /></a><span class="contact-number">{{ $site['contact']['whatsapp'] }}</span>@else <div class="unavailable-note"><x-dpe.icon name="clock" /><span>Nomor WhatsApp belum tersedia.</span></div>@endif
    <div class="contact-hours"><x-dpe.icon name="clock" /><div><h3>Jam pelayanan</h3><p>{{ $site['contact']['service_hours'] ?: 'Informasi jam pelayanan belum tersedia.' }}</p></div></div>
</div><div class="contact-secondary">
    <article class="contact-card"><span class="icon-tile"><x-dpe.icon name="mail" /></span><div><h2>Email</h2>@if ($site['contact']['email'])<a class="text-link" href="mailto:{{ $site['contact']['email'] }}">{{ $site['contact']['email'] }} <x-dpe.icon name="arrow" /></a>@else <p>Alamat email belum tersedia.</p>@endif</div></article>
    @if ($site['contact']['phone'])<article class="contact-card"><span class="icon-tile"><x-dpe.icon name="phone" /></span><div><h2>Telepon</h2><p>{{ $site['contact']['phone'] }}</p></div></article>@endif
    <article class="contact-card"><span class="icon-tile"><x-dpe.icon name="globe" /></span><div><h2>Media sosial</h2>@if ($site['contact']['socials'])<div class="social-links">@foreach ($site['contact']['socials'] as $social)<a class="text-link" href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer">{{ $social['name'] }} <x-dpe.icon name="external" /></a>@endforeach</div>@else <p>Informasi media sosial belum tersedia.</p>@endif</div></article>
    <article class="contact-card"><span class="icon-tile"><x-dpe.icon name="pin" /></span><div><h2>Kenali cabang DPE</h2><p>Lihat alamat dan informasi pelayanan setiap cabang.</p><a class="text-link" href="{{ route('public.branches') }}">Temukan cabang <x-dpe.icon name="arrow" /></a></div></article>
</div></section>
@endsection
