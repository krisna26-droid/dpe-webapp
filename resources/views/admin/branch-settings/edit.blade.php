@extends('layouts.dashboard')
@section('title', 'Ubah Alamat Cabang')
@section('heading', 'Ubah Alamat Cabang')
@section('description', 'Perbarui alamat untuk cabang yang berada dalam penugasan Anda.')
@section('content')
    <div class="dpe-settings-page">
        <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Admin / Pengaturan Cabang</p><h2>{{ $branch->name }}</h2><p>{{ $branch->code }} · {{ $branch->timezone_name }}</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.branch-settings.index') }}"><x-dashboard.icon name="back" />Kembali ke pengaturan cabang</a></section>
        <form method="POST" action="{{ route('admin.branch-settings.update', $branch) }}" class="dpe-dashboard-form dpe-settings-form" data-confirm-form data-confirm-title="Simpan alamat cabang?" data-confirm-message="Pastikan alamat sudah sesuai dengan lokasi cabang." data-confirm-action="Ya, simpan alamat">
            @csrf @method('PUT')<input name="record_version" type="hidden" value="{{ old('record_version', $recordVersion) }}">
            <div class="dpe-dashboard-field"><label for="branch-address">Alamat lengkap cabang</label><textarea id="branch-address" name="address" rows="5" maxlength="5000" required>{{ old('address', $branch->address) }}</textarea><small>Isi jalan, nomor, desa/kelurahan, kecamatan, dan kota agar lokasi mudah ditemukan.</small></div>
            <div class="dpe-settings-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Simpan alamat</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.branch-settings.index') }}">Batal</a></div>
        </form>
    </div>
@endsection
