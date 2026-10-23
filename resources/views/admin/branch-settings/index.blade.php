@extends('layouts.dashboard')
@section('title', 'Pengaturan Cabang')
@section('heading', 'Pengaturan Cabang')
@section('description', 'Lihat identitas cabang yang Anda kelola dan perbarui alamatnya.')
@section('content')
    <div class="dpe-settings-page">
        <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Admin / Cabang Saya</p><h2>Cabang yang Anda kelola.</h2><p>Nama, kode, zona waktu, tanggal rekap, dan status cabang dikelola Super Admin.</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route(auth()->user()->role_code.'.dashboard') }}"><x-dashboard.icon name="back" />Kembali ke dashboard</a></section>
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Identitas cabang</h2><p>Tanggal rekap pembayaran merupakan tanggal pengumpulan rekap, bukan jatuh tempo tagihan.</p></div></header>
            @if ($branches->isEmpty())<x-dashboard.empty title="Belum ada cabang yang ditugaskan" message="Hubungi Super Admin untuk memeriksa penugasan Admin Cabang Anda." icon="building" />
            @else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-settings-table"><thead><tr><th>Cabang</th><th>Alamat</th><th>Zona waktu</th><th>Rekap pembayaran</th><th>Absensi di portal</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($branches as $branch)<tr><td><strong>{{ $branch->name }}</strong><small>{{ $branch->code }}</small></td><td class="dpe-settings-address">{{ $branch->address ?: 'Alamat belum diisi' }}</td><td>{{ $branch->timezone_name }}</td><td>Tanggal {{ $branch->payment_recap_day }} setiap bulan</td><td><x-dashboard.status :status="$branch->portal_attendance_visible ? 'visible' : 'hidden'" :label="$branch->portal_attendance_visible ? 'Ditampilkan' : 'Disembunyikan'" /></td><td><x-dashboard.status :status="$branch->is_active" :label="$branch->is_active ? 'Aktif' : 'Nonaktif'" /></td><td><div class="dpe-settings-actions">@if ($branch->is_active)<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.branch-settings.edit', $branch) }}">Ubah alamat</a>@else<span>Hubungi Super Admin</span>@endif</div></td></tr>@endforeach
            </tbody></table></div>@endif
        </article>
    </div>
@endsection
