@extends('layouts.dashboard')
@section('title', 'Review Pembayaran')
@section('heading', 'Review Pembayaran')
@section('description', 'Validasi bukti transfer hanya oleh Super Admin.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    <div class="dpe-portal-actions dpe-portal-spaced"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.monthly-charges.index') }}">Kembali ke Tagihan Bulanan</a></div>
    <article class="dpe-dashboard-card dpe-portal-spaced">
        <div class="dpe-dashboard-card__body"><form method="GET" class="dpe-portal-filter">
            <div class="dpe-dashboard-field"><label for="payment-status">Status</label><select id="payment-status" name="status">@foreach (['pending' => 'Menunggu Validasi', 'approved' => 'Tervalidasi', 'rejected' => 'Ditolak', 'all' => 'Semua Status'] as $code => $label)<option value="{{ $code }}" @selected($status === $code)>{{ $label }}</option>@endforeach</select></div>
            <div class="dpe-dashboard-field"><label for="payment-student">Nama siswa</label><input id="payment-student" name="q" type="search" maxlength="100" value="{{ $search }}" placeholder="Cari siswa"></div>
            <div class="dpe-portal-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.payment-review.index') }}">Reset</a></div>
        </form></div>
    </article>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Bukti pembayaran</h2><p>Periksa gambar, siswa, cabang, dan bulan sebelum memutuskan.</p></div></header>
        @if ($proofs->isEmpty())
            <x-dashboard.empty title="Belum ada bukti sesuai filter" message="Bukti yang dikirim melalui portal siswa akan masuk ke antrean ini." icon="wallet" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-portal-table"><thead><tr><th>Siswa / Bulan</th><th>Status</th><th>Cabang / Dikirim</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($proofs as $proof)
                    <tr><td><strong>{{ $proof->charge?->student?->full_name }}</strong><small>{{ $proof->charge?->charge_month?->locale('id')->translatedFormat('F Y') }}</small></td><td><span class="dpe-dashboard-badge">{{ \App\Services\PortalPaymentService::LABELS[$proof->status] ?? 'Perlu Pemeriksaan Admin' }}</span></td><td>{{ $proof->charge?->branch?->name }}<small>{{ $proof->submitted_at?->format('d M Y H:i') }}</small></td><td><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.payment-review.show', $proof) }}">Periksa Bukti</a></td></tr>
                @endforeach
            </tbody></table></div>
            <x-dashboard.pagination :paginator="$proofs" />
        @endif
    </article>
@endsection

