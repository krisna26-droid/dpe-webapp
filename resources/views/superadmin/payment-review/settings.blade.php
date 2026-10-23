@extends('layouts.dashboard')
@section('title', 'Pengaturan Portal')
@section('heading', 'Pengaturan Portal')
@section('description', 'Visibilitas kehadiran siswa pada portal dan laporan bulanan per cabang.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    <div class="dpe-portal-actions dpe-portal-spaced"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.dashboard') }}">Kembali ke Dashboard</a></div>
    @if ($errors->has('expected_snapshot'))<p class="dpe-dashboard-alert dpe-dashboard-alert--error">{{ $errors->first('expected_snapshot') }} <a href="{{ route('superadmin.portal-settings.index') }}">Muat halaman terbaru</a></p>@endif
    <div class="dpe-portal-stack">
        @forelse ($branches as $row)
            <article class="dpe-dashboard-card">
                <header class="dpe-dashboard-card__head"><div><h2>{{ $row['branch']->name }}</h2><p>{{ $row['branch']->is_active ? 'Cabang aktif' : 'Cabang nonaktif' }}</p></div><span class="dpe-dashboard-badge">{{ $row['branch']->portal_attendance_visible ? 'Absensi Ditampilkan' : 'Absensi Disembunyikan' }}</span></header>
                <div class="dpe-dashboard-card__body">
                    <form method="POST" action="{{ route('superadmin.portal-settings.save', $row['branch']) }}" class="dpe-portal-settings-form">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="expected_snapshot" value="{{ $row['snapshot'] }}"><input type="hidden" name="visible" value="0">
                        <label class="dpe-portal-toggle"><input type="checkbox" name="visible" value="1" @checked($row['branch']->portal_attendance_visible)> Tampilkan absensi untuk seluruh anak di cabang ini</label>
                        <p>Pengaturan berlaku untuk portal anak dan laporan bulanan. Pencatatan internal tetap tersedia. PDF final mengikuti template tanpa absensi.</p>
                        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($errors->has('expected_snapshot'))>Simpan Pengaturan Cabang</button>
                    </form>
                </div>
            </article>
        @empty
            <x-dashboard.empty title="Belum ada cabang" message="Tambahkan cabang sebelum mengatur portal." icon="building" />
        @endforelse
    </div>
@endsection

