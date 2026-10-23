@extends('layouts.dashboard')
@section('title', 'Konten Publik')
@section('heading', 'Konten Publik')
@section('description', 'Kelola informasi website melalui draft, pratinjau, dan publikasi.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/website-content.css') }}?v={{ filemtime(public_path('assets/dpe/website-content.css')) }}">
@endpush
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Website DPE</p><h2>Siapkan perubahan sebelum tayang.</h2><p>Simpan Draft → Pratinjau → Tayangkan. Setiap bagian dapat ditayangkan sendiri.</p></div>
        <div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('home') }}" target="_blank" rel="noopener noreferrer"><x-dashboard.icon name="globe" />Lihat website</a></div>
    </section>
    <nav class="dpe-website-tabs" aria-label="Bagian website">
        @foreach (\App\Services\WebsiteContentDefinition::TABS as $value => $label)<a class="dpe-website-tab{{ $tab === $value ? ' is-active' : '' }}" href="{{ route('superadmin.website.index', ['tab' => $value]) }}" @if ($tab === $value) aria-current="page" @endif>{{ $label }}</a>@endforeach
    </nav>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>{{ \App\Services\WebsiteContentDefinition::TABS[$tab] }}</h2><p>Draft tidak mengubah informasi yang sedang tayang.</p></div>
            <div class="dpe-website-actions">
                @if ($tab === 'home')<a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.website.create', ['kind' => 'home.benefit']) }}">Tambah keunggulan</a>@endif
                @if ($tab === 'about')<a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.website.create', ['kind' => 'about.approach']) }}">Tambah cara belajar</a>@endif
                @if (in_array($tab, ['program', 'branch']))<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.'.($tab === 'program' ? 'programs' : 'branches').'.index') }}">Kelola data {{ $tab === 'program' ? 'program' : 'cabang' }}</a>@endif
            </div>
        </header>
        <div class="dpe-dashboard-card__body">
            <form method="GET" action="{{ route('superadmin.website.index') }}" class="dpe-website-filter"><input type="hidden" name="tab" value="{{ $tab }}"><div class="dpe-dashboard-field"><label for="website-query">Cari bagian atau nama</label><input id="website-query" name="q" value="{{ $query }}" maxlength="120" placeholder="Cari konten website"></div><div class="dpe-website-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.website.index', ['tab' => $tab]) }}">Reset</a></div></form>
            @if (in_array($tab, ['program', 'branch']))<p class="dpe-website-hint">Nama dan status aktif mengikuti data operasional. Deskripsi, foto, urutan, dan visibilitas website diatur di sini.</p>@endif
        </div>
        @if ($rows->isEmpty())<x-dashboard.empty title="Belum ada bagian sesuai pilihan" message="Periksa pencarian atau tambahkan data melalui menu yang tersedia." icon="globe" />
        @else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-website-table"><thead><tr><th>Bagian / nama</th><th>Website</th><th>Draft</th><th>Urutan</th><th>Aksi</th></tr></thead><tbody>
            @foreach ($rows as $row)<tr><td><strong>{{ $row['name'] }}</strong><small>{{ $row['label'] }}</small></td><td><span class="dpe-dashboard-badge {{ $row['live']?->is_published && $row['master_active'] ? 'dpe-dashboard-badge--success' : '' }}">{{ $row['live']?->is_published && $row['master_active'] ? 'Tayang' : ($row['live'] ? 'Disembunyikan' : 'Belum tayang') }}</span>@if (! $row['master_active'])<small>{{ $tab === 'program' ? 'Program' : 'Cabang' }} nonaktif</small>@endif</td><td>@if ($row['draft'])<span class="dpe-dashboard-badge dpe-dashboard-badge--warning">Draft tersimpan</span><small>{{ $row['draft']->saved_at->format('d/m/Y H:i') }}<br>{{ $row['draft']->saved_by_name }}</small>@else<span>—</span>@endif</td><td>{{ $row['order'] }}</td><td><div class="dpe-dashboard-table-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.website.edit', $row['key']) }}">{{ $row['draft'] ? 'Periksa draft' : 'Edit konten' }}</a>@if ($row['draft'])<a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.website.preview', $row['key']) }}" target="_blank" rel="noopener noreferrer">Pratinjau</a>@endif</div></td></tr>@endforeach
        </tbody></table></div><x-dashboard.pagination :paginator="$rows" />@endif
    </article>
@endsection
