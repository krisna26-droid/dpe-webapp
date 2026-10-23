@php
    $studentName = $monthlyCharge->student?->full_name ?: 'Siswa tidak ditemukan';
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

@extends('layouts.dashboard')

@section('title', 'Detail Tagihan Bulanan')
@section('heading', 'Detail Tagihan Bulanan')
@section('description', 'Periksa nilai tagihan, jatuh tempo, dan riwayat bukti pembayaran siswa.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Laporan & Keuangan / Tagihan</p>
            <h2>{{ $studentName }}</h2>
            <p>{{ $monthlyCharge->charge_month?->format('F Y') ?: 'Periode belum tersedia' }} · {{ $monthlyCharge->branch?->name ?: 'Cabang belum tersedia' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.monthly-charges.edit', $monthlyCharge) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit tagihan</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.monthly-charges.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--two">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Ringkasan tagihan</h2>
                    <p>Data utama kewajiban pembayaran siswa.</p>
                </div>
                <span class="dpe-dashboard-badge"><x-dashboard.icon name="wallet" /> Tagihan</span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Siswa</p>
                        <p class="dpe-dashboard-detail__value">{{ $studentName }}</p>
                        @if ($monthlyCharge->student?->portalUser?->username)
                            <p class="dpe-dashboard-field__hint">{{ $monthlyCharge->student->portalUser->username }}</p>
                        @endif
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Cabang</p>
                        <p class="dpe-dashboard-detail__value">{{ $monthlyCharge->branch?->name ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Bulan tagihan</p>
                        <p class="dpe-dashboard-detail__value">{{ $monthlyCharge->charge_month?->format('F Y') ?: '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Nominal</p>
                        <p class="dpe-dashboard-detail__value">{{ $monthlyCharge->amount_idr === null ? 'Belum ditetapkan' : 'Rp '.number_format((float) $monthlyCharge->amount_idr, 2, ',', '.') }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Jatuh tempo</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $monthlyCharge->due_on?->format('d M Y') ?: 'Tidak ditentukan' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Dibuat</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $monthlyCharge->created_at?->format('d M Y H:i') ?: '—' }}</p>
                    </div>
                </div>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Status pembayaran</h2>
                    <p>Status ditentukan dari bukti pembayaran yang tercatat.</p>
                </div>
                <span class="dpe-dashboard-badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </header>
            <div class="dpe-dashboard-card__body">
                @if ($approvedProof)
                    <div class="dpe-dashboard-alert dpe-dashboard-alert--success" role="status">
                        <x-dashboard.icon name="check" />
                        <span>Bukti pembayaran sudah disetujui.</span>
                    </div>
                @elseif ($latestProof && $latestStatus === 'pending')
                    <div class="dpe-dashboard-alert" role="status">
                        <x-dashboard.icon name="clock" />
                        <span>Bukti pembayaran terakhir masih menunggu review.</span>
                    </div>
                @elseif ($latestProof && $latestStatus === 'rejected')
                    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
                        <x-dashboard.icon name="warning" />
                        <span>Bukti pembayaran terakhir ditolak dan perlu ditindaklanjuti.</span>
                    </div>
                @else
                    <div class="dpe-dashboard-state dpe-dashboard-state--empty">
                        <div class="dpe-dashboard-state__icon"><x-dashboard.icon name="wallet" /></div>
                        <div class="dpe-dashboard-state__body">
                            <h3>Belum ada bukti pembayaran</h3>
                            <p>Belum ada bukti yang tercatat untuk tagihan ini.</p>
                        </div>
                    </div>
                @endif

                @if ($latestProof)
                    <div class="dpe-dashboard-detail-grid" style="margin-top: 20px;">
                        <div class="dpe-dashboard-detail">
                            <p class="dpe-dashboard-detail__label">Bukti terakhir</p>
                            <p class="dpe-dashboard-detail__value">{{ $latestProof->submitted_at?->format('d M Y H:i') ?: '—' }}</p>
                        </div>
                        <div class="dpe-dashboard-detail">
                            <p class="dpe-dashboard-detail__label">Status bukti</p>
                            <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ ucfirst($latestStatus ?: '—') }}</p>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('superadmin.monthly-charges.destroy', $monthlyCharge) }}" class="dpe-dashboard-form-actions" data-confirm-form data-confirm-title="Hapus tagihan ini?" data-confirm-message="Tagihan hanya dapat dihapus bila belum memiliki bukti pembayaran. Riwayat pembayaran harus dipertahankan." data-confirm-action="Hapus tagihan">
                    @csrf
                    @method('DELETE')
                    <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit">
                        <x-dashboard.icon name="trash" />
                        <span>Hapus tagihan</span>
                    </button>
                </form>
            </div>
        </article>
    </section>

    <article class="dpe-dashboard-card dpe-dashboard-card--spaced">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Riwayat bukti pembayaran</h2>
                <p>{{ $proofs->count() }} bukti tercatat untuk tagihan ini.</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="report" /> Review pembayaran</span>
        </header>
        @if ($proofs->isEmpty())
            <x-dashboard.empty
                title="Belum ada riwayat pembayaran"
                message="Bukti pembayaran akan tampil di sini setelah dikirim melalui alur pembayaran."
                icon="report"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Dikirim</th>
                            <th>Status</th>
                            <th>File</th>
                            <th>Pengunggah</th>
                            <th>Catatan penolakan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($proofs as $proof)
                            @php
                                $proofStatus = strtolower((string) $proof->status);
                                $proofClass = match ($proofStatus) {
                                    'approved' => 'dpe-dashboard-badge--success',
                                    'rejected' => 'dpe-dashboard-badge--danger',
                                    default => 'dpe-dashboard-badge--warning',
                                };
                                $proofLabel = match ($proofStatus) {
                                    'approved' => 'Disetujui',
                                    'rejected' => 'Ditolak',
                                    'pending' => 'Menunggu review',
                                    default => ucfirst($proofStatus ?: 'Belum diketahui'),
                                };
                            @endphp
                            <tr>
                                <td>{{ $proof->submitted_at?->format('d M Y H:i') ?: '—' }}</td>
                                <td><span class="dpe-dashboard-badge {{ $proofClass }}">{{ $proofLabel }}</span></td>
                                <td>{{ $proof->imageFile?->original_name ?: 'File tidak ditemukan' }}</td>
                                <td>{{ $proof->uploadedBy?->full_name ?: ($proof->uploadedBy?->username ?: '—') }}</td>
                                <td>{{ $proof->rejection_reason ?: '—' }}</td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.payment-review.show', $proof) }}">Periksa Bukti</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>
@endsection
