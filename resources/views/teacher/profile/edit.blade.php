@php
    $fieldValue = function ($name, $fallback = '') { $value = old($name, $fallback); return is_scalar($value) || $value === null ? $value : ''; };
@endphp
@extends('layouts.dashboard')
@section('title', 'Edit Profil Saya')
@section('heading', 'Edit Profil Saya')
@section('description', 'Perbarui nama, email, kontak WhatsApp, dan foto profil Anda.')
@push('scripts')<script src="{{ asset('assets/dpe/own-account.js') }}?v={{ filemtime(public_path('assets/dpe/own-account.js')) }}" defer></script>@endpush
@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Teacher / Profil Saya</p><h2>Edit identitas dan kontak.</h2><p>Nama pengguna, status, dan penugasan diatur oleh Admin.</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.profile.show') }}"><x-dashboard.icon name="back" />Kembali ke Profil Saya</a></section>
    <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Data profil</h2><p>Kolom bertanda * wajib diisi.</p></div></header><div class="dpe-dashboard-card__body">
        <form method="POST" action="{{ route('teacher.profile.update') }}" enctype="multipart/form-data" class="dpe-dashboard-form dpe-account-stack">
            @csrf @method('PUT')<input type="hidden" name="record_version" value="{{ $fieldValue('record_version', $recordVersion) }}">
            <div class="dpe-account-upload">
                <div class="dpe-account-avatar dpe-account-avatar--edit"><span aria-hidden="true">{{ mb_strtoupper(mb_substr($actor->full_name ?: $actor->username, 0, 2)) }}</span><img data-account-photo-preview data-account-avatar-image @if($teacher->photoFile) src="{{ route('teacher.profile.photo') }}" @else hidden @endif alt="Pratinjau foto profil"></div>
                <div class="dpe-dashboard-field"><label for="profile-photo">Foto profil</label><input id="profile-photo" data-account-photo type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="profile-photo-hint"><p id="profile-photo-hint" data-account-photo-hint class="dpe-dashboard-field__hint" aria-live="polite">Foto JPG, PNG, atau WebP. Maksimal 2 MB dan 4096 × 4096 piksel.</p>@error('photo')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
            </div>
            <div class="dpe-dashboard-form-grid">
                <div class="dpe-dashboard-field"><label for="profile-name">Nama lengkap <span>*</span></label><input id="profile-name" name="full_name" type="text" required maxlength="255" autocomplete="name" value="{{ $fieldValue('full_name', $actor->full_name) }}">@error('full_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                <div class="dpe-dashboard-field"><label for="profile-email">Email</label><input id="profile-email" name="email" type="email" maxlength="255" autocomplete="email" value="{{ $fieldValue('email', $actor->email) }}">@error('email')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                <div class="dpe-dashboard-field"><label for="profile-whatsapp">Nomor WhatsApp <span>*</span></label><input id="profile-whatsapp" name="whatsapp_number" type="tel" required maxlength="32" autocomplete="tel" placeholder="081234567890" value="{{ $fieldValue('whatsapp_number', $teacher->whatsapp_number) }}">@error('whatsapp_number')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                <div class="dpe-dashboard-field"><label for="profile-username">Nama pengguna</label><input id="profile-username" type="text" readonly value="{{ $actor->username }}"><p class="dpe-dashboard-field__hint">Hubungi Admin untuk perubahan nama pengguna.</p></div>
            </div>
            <div class="dpe-account-notice"><p>Gunakan nomor WhatsApp aktif yang dapat dihubungi. Nomor ini menjadi kontak pada profil Anda.</p></div>
            <div class="dpe-account-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Simpan profil</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.profile.show') }}">Batal</a></div>
        </form>
    </div></article>
@endsection
