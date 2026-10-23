@extends('layouts.public')
@section('title', $program['name'])
@section('content')
<section class="container detail-heading"><a class="text-link muted-link" href="{{ isset($websitePreview) ? $websitePreview['homeUrl'].'#program' : route('public.programs') }}"><x-dpe.icon name="back" /> Semua program</a><span class="eyebrow">{{ $program['type_label'] }}</span><h1>{{ $program['name'] }}</h1><p class="prose">{{ $program['description'] ?: 'Konsultasikan program ini untuk mengetahui materi dan pilihan belajar yang tersedia.' }}</p></section>
<section class="container detail-grid catalog-section"><div class="content-card program-content">
    @if ($program['image'])<img class="program-detail-image" src="{{ $program['image'] }}" alt="{{ $program['name'] }}" width="800" height="480">@endif
    <span class="icon-tile"><x-dpe.icon name="book" /></span><h2>Kenali program ini</h2>
    @if ($program['audience'])<div class="detail-field"><h3>Untuk siapa?</h3><p class="prose">{{ $program['audience'] }}</p></div>@endif
    @if ($program['features'])<div class="detail-field"><h3>Fokus belajar</h3><ul class="check-list">@foreach ($program['features'] as $feature)<li><x-dpe.icon name="check" /><span>{{ $feature }}</span></li>@endforeach</ul></div>@endif
    @if ($program['duration_label'])<div class="detail-field"><h3>Jadwal & durasi</h3><p>{{ $program['duration_label'] }}</p></div>@endif
    @unless ($program['audience'] || $program['features'] || $program['duration_label'])<p>Rincian materi dan jadwal belum tersedia. Silakan konsultasikan kebutuhanmu melalui kontak DPE.</p>@endunless
</div><aside class="program-consult"><span class="icon-tile"><x-dpe.icon name="message" /></span><h2>Tertarik dengan {{ $program['name'] }}?</h2><p>Diskusikan kebutuhan belajar, jadwal, dan langkah pendaftaran melalui kontak DPE.</p><a class="button button-primary" href="{{ isset($websitePreview) ? $websitePreview['homeUrl'].'#kontak' : route('public.contact', ['program' => $program['name']]) }}">Konsultasi program <x-dpe.icon name="arrow" /></a><a class="text-link" href="{{ isset($websitePreview) ? $websitePreview['homeUrl'].'#cabang' : route('public.branches') }}">Lihat cabang DPE <x-dpe.icon name="pin" /></a></aside></section>
@endsection
