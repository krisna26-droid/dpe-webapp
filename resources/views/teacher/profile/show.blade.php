@extends('layouts.dashboard')
@section('title', 'Profil Saya')
@section('heading', 'Profil Saya')
@section('description', 'Identitas, kontak, dan penugasan mengajar Anda.')
@push('scripts')<script src="{{ asset('assets/dpe/own-account.js') }}?v={{ filemtime(public_path('assets/dpe/own-account.js')) }}" defer></script>@endpush
@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Teacher / Akun Saya</p><h2>Profil pengajar Anda.</h2><p>Perbarui identitas dan kontak agar informasi Anda tetap sesuai.</p></div><div class="dpe-dashboard-page-header__actions">@if($canEdit)<a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('teacher.profile.edit') }}">Edit profil</a>@endif<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.settings.index') }}">Pengaturan akun</a></div></section>
    <div class="dpe-account-stack">
        <article class="dpe-dashboard-card"><div class="dpe-dashboard-card__body"><div class="dpe-account-identity">
            <div class="dpe-account-avatar"><span aria-hidden="true">{{ mb_strtoupper(mb_substr($actor->full_name ?: $actor->username, 0, 2)) }}</span>@if($teacher?->photoFile)<img data-account-avatar-image src="{{ route('teacher.profile.photo', ['v' => $teacher->photo_file_id]) }}" alt="Foto profil {{ $actor->full_name }}">@endif</div>
            <div class="dpe-account-identity__copy"><h2>{{ $actor->full_name }}</h2><span class="dpe-dashboard-badge {{ $canEdit ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $canEdit ? 'Teacher aktif' : ($teacher ? 'Profil Teacher nonaktif' : 'Profil Teacher belum disiapkan') }}</span><p>{{ $actor->username }}</p></div>
        </div></div></article>
        @if(!$canEdit)<div class="dpe-account-notice"><p>{{ $teacher ? 'Profil Teacher Anda sedang nonaktif. Hubungi Admin untuk memeriksa status penugasan dan akses mengajar.' : 'Akun Anda sudah tersedia, tetapi profil Teacher belum disiapkan oleh Admin. Hubungi Admin untuk melengkapi profil dan penugasan.' }}</p></div>@endif
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Identitas & kontak</h2><p>Informasi pada akun Teacher yang sedang login.</p></div></header><div class="dpe-dashboard-card__body"><div class="dpe-dashboard-detail-grid">
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Nama lengkap</p><p class="dpe-dashboard-detail__value">{{ $actor->full_name }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Nama pengguna</p><p class="dpe-dashboard-detail__value">{{ $actor->username }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Email</p><p class="dpe-dashboard-detail__value">{{ $actor->email ?: 'Belum diisi' }}</p></div>
            <div class="dpe-dashboard-detail"><p class="dpe-dashboard-detail__label">Nomor WhatsApp</p><p class="dpe-dashboard-detail__value">{{ $teacher?->whatsapp_number ?: 'Belum diisi' }}</p></div>
        </div><div class="dpe-account-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.dashboard') }}"><x-dashboard.icon name="back" />Kembali ke dashboard</a></div></div></article>
        @if($canEdit)
            <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Cabang penugasan saat ini</h2><p>Cabang dengan penugasan Anda yang berlaku hari ini.</p></div></header>
                @if($branches->isEmpty())<x-dashboard.empty title="Belum ada cabang penugasan hari ini" message="Penugasan diatur oleh Admin. Jadwal mendatang tetap dapat dilihat melalui Jadwal Mengajar." icon="building" />
                @else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-account-table"><thead><tr><th>Cabang</th><th>Zona waktu</th><th>Status cabang</th></tr></thead><tbody>@foreach($branches as $branch)<tr><td>{{ $branch->name }}</td><td>{{ $branch->timezone_name }}</td><td><span class="dpe-dashboard-badge">{{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}</span></td></tr>@endforeach</tbody></table></div>@endif
            </article>
            <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Kelas & program saya</h2><p>Kelompok kelas aktif yang menetapkan Anda sebagai Teacher utama.</p></div></header>
                @if($groups->isEmpty())<x-dashboard.empty title="Belum ada kelompok kelas aktif" message="Siswa dan sesi private/pengganti dapat dilihat pada Siswa Binaan dan Jadwal Mengajar." icon="book" />
                @else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-account-table"><thead><tr><th>Kelas</th><th>Program</th><th>Cabang</th></tr></thead><tbody>@foreach($groups as $group)<tr><td>{{ $group->name }}</td><td>{{ $group->program?->name }}</td><td>{{ $group->branch?->name }}</td></tr>@endforeach</tbody></table></div>@endif
                <div class="dpe-dashboard-card__body"><div class="dpe-operations-actions"><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('teacher.schedule.index') }}">Jadwal Mengajar</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.students.index') }}">Siswa Binaan</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.availability.index') }}">Slot Ketersediaan</a></div></div>
            </article>
        @endif
    </div>
    @if($canEdit)
        @include('report-signatures.form', ['signatureScope' => 'teacher', 'signatureRouteBase' => 'teacher.profile.signature'])
    @endif
@endsection
