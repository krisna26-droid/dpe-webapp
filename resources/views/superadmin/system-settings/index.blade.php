@extends('layouts.dashboard')
@section('title', 'Pengaturan Sistem')
@section('heading', 'Pengaturan Sistem')
@section('description', 'Kelola identitas internal, nilai awal laporan, dan pengingat aplikasi.')
@section('content')
    <div class="dpe-settings-page">
        <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Super Admin / Sistem</p><h2>Pengaturan lembaga.</h2><p>Periksa nilai yang akan digunakan sebelum menyimpan.</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.dashboard') }}"><x-dashboard.icon name="back" />Kembali ke dashboard</a></section>
        @if (! $setting->exists)<div class="dpe-settings-note" role="status">Pengaturan sistem belum disiapkan. Nilai di bawah adalah usulan awal; simpan untuk menggunakannya.</div>@endif
        <form method="POST" action="{{ route('superadmin.settings.update') }}" class="dpe-dashboard-form dpe-settings-form" data-confirm-form data-confirm-title="Simpan pengaturan sistem?" data-confirm-message="Nilai awal video dan tenggat digunakan saat menyiapkan periode berikutnya. Periode yang sudah dibuat tetap memakai nilai tersimpan." data-confirm-action="Ya, simpan pengaturan">
            @csrf @method('PUT')
            <input type="hidden" name="record_version" value="{{ old('record_version', $recordVersion) }}">
            <section class="dpe-settings-section"><header><h3>Identitas internal lembaga</h3><p>Identitas dan konten halaman publik dikelola melalui menu Konten Publik.</p></header>
                <div class="dpe-settings-fields">
                    <div class="dpe-dashboard-field"><label for="system-name">Nama lembaga</label><input id="system-name" name="organization_name" maxlength="255" value="{{ old('organization_name', $setting->organization_name) }}" required></div>
                    <div class="dpe-dashboard-field"><label for="system-email">Email kontak</label><input id="system-email" name="contact_email" type="email" maxlength="255" value="{{ old('contact_email', $setting->contact_email) }}"></div>
                    <div class="dpe-dashboard-field"><label for="system-phone">Telepon kontak</label><input id="system-phone" name="contact_phone" type="tel" maxlength="255" value="{{ old('contact_phone', $setting->contact_phone) }}"></div>
                    <div class="dpe-dashboard-field dpe-settings-wide"><label for="system-description">Deskripsi lembaga</label><textarea id="system-description" name="organization_description" rows="4" maxlength="5000">{{ old('organization_description', $setting->organization_description) }}</textarea></div>
                </div>
            </section>
            <section class="dpe-settings-section"><header><h3>Nilai awal laporan baru</h3><p>Perubahan ini berlaku untuk periode yang disiapkan setelah pengaturan disimpan. Target video bulan pertama tetap ditetapkan Admin sesuai jadwal siswa.</p></header>
                <div class="dpe-settings-fields">
                    <div class="dpe-dashboard-field"><label for="system-due">Tanggal tenggat laporan bulanan</label><input id="system-due" name="default_report_due_day" type="number" min="1" max="31" value="{{ old('default_report_due_day', $setting->default_report_due_day) }}" required><small>Tanggal 1–31. Jika bulan lebih pendek, tenggat jatuh pada tanggal terakhir bulan tersebut.</small></div>
                    <div class="dpe-dashboard-field"><label for="system-video">Target video per bulan</label><input id="system-video" name="default_monthly_video_target" type="number" min="0" max="32767" value="{{ old('default_monthly_video_target', $setting->default_monthly_video_target) }}" required><small>Nilai awal untuk bulan berikutnya. Isi 0 jika tidak ada target video.</small></div>
                </div>
            </section>
            <section class="dpe-settings-section"><header><h3>Pengingat dalam aplikasi</h3><p>Atur pengingat tenggat laporan dan kelengkapan video. Pemberitahuan pembayaran, persetujuan, dan kehadiran tetap tersedia.</p></header>
                <input type="hidden" name="in_app_reminders_enabled" value="0">
                <label class="dpe-settings-check" for="system-reminders"><input id="system-reminders" name="in_app_reminders_enabled" type="checkbox" value="1" @checked((bool) old('in_app_reminders_enabled', $setting->in_app_reminders_enabled))><span>Aktifkan pengingat tenggat dan video</span></label>
            </section>
            <div class="dpe-settings-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Simpan pengaturan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.settings.index') }}">Muat ulang pengaturan</a></div>
        </form>
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Pengaturan terkait</h2><p>Buka pengelolaan sesuai kebutuhan.</p></div></header><div class="dpe-dashboard-card__body dpe-settings-links">
            @foreach (['superadmin.programs.index' => 'Program belajar', 'superadmin.learning-skills.index' => 'Kompetensi & skill', 'superadmin.users.index' => 'Pengguna & hak akses', 'superadmin.portal-settings.index' => 'Visibilitas absensi portal', 'superadmin.report-template.edit' => 'Template rapor', 'superadmin.website.index' => 'Konten publik'] as $target => $label)<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($target) }}">{{ $label }}</a>@endforeach
        </div></article>
    </div>
@endsection
