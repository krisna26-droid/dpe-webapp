@extends('layouts.dashboard')

@section('title', 'Tagihan Bulanan')
@section('heading', 'Tagihan Bulanan')
@section('description', 'Kelola tagihan bulanan siswa dan pantau status bukti pembayarannya.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Laporan & Keuangan / Tagihan</p>
            <h2>Tagihan bulanan</h2>
            <p>Catat nominal tagihan per siswa, tetapkan jatuh tempo, dan lihat status pembayaran yang masuk.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.monthly-charges.create') }}">
                <x-dashboard.icon name="wallet" />
                <span>Tambah tagihan</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar tagihan</h2>
                <p>{{ $monthlyCharges->total() }} tagihan tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="wallet" /> Keuangan siswa</span>
        </header>

        @if ($monthlyCharges->count() === 0)
            <x-dashboard.empty
                title="Belum ada tagihan bulanan"
                message="Tambahkan tagihan pertama untuk mulai mencatat kewajiban pembayaran siswa."
                icon="wallet"
                :action-url="route('superadmin.monthly-charges.create')"
                action-label="Tambah tagihan"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th>Siswa</th>
                            <th>Cabang</th>
                            <th>Nominal</th>
                            <th>Jatuh tempo</th>
                            <th>Status pembayaran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($monthlyCharges as $monthlyCharge)
                            @php
                                $proofs = $monthlyCharge->paymentProofs;
                                $approvedProof = $proofs->firstWhere('status', 'approved');
                                $latestProof = $proofs->first();
                                $latestStatus = strtolower((string) $latestProof?->status);
                                $statusLabel = 'Belum dibayar';
                                $statusClass = 'dpe-dashboard-badge--warning';

                                if ($approvedProof) {
                                    $statusLabel = 'Lunas';
                                    $statusClass = 'dpe-dashboard-badge--success';
                                } elseif ($latestStatus === 'pending') {
                                    $statusLabel = 'Menunggu review';
                                } elseif ($latestStatus === 'rejected') {
                                    $statusLabel = 'Bukti ditolak';
                                    $statusClass = 'dpe-dashboard-badge--danger';
                                } elseif ($monthlyCharge->due_on?->isBefore(now()->startOfDay())) {
                                    $statusLabel = 'Terlambat';
                                    $statusClass = 'dpe-dashboard-badge--danger';
                                }
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('superadmin.monthly-charges.show', $monthlyCharge) }}">
                                        <strong>{{ $monthlyCharge->charge_month?->format('M Y') ?: '—' }}</strong>
                                    </a>
                                </td>
                                <td>{{ $monthlyCharge->student?->full_name ?: 'Siswa tidak ditemukan' }}</td>
                                <td>{{ $monthlyCharge->branch?->name ?: 'Cabang tidak ditemukan' }}</td>
                                <td><strong>{{ $monthlyCharge->amount_idr === null ? 'Belum ditetapkan' : 'Rp '.number_format((float) $monthlyCharge->amount_idr, 2, ',', '.') }}</strong></td>
                                <td>{{ $monthlyCharge->due_on?->format('d M Y') ?: 'Tidak ditentukan' }}</td>
                                <td><span class="dpe-dashboard-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                <td>
                                    <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.monthly-charges.show', $monthlyCharge) }}">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$monthlyCharges" />
        @endif
    </article>
@endsection
