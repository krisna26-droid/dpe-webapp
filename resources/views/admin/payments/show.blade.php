@extends('layouts.dashboard')
@section('title', 'Detail Pembayaran Cabang')
@section('heading', 'Detail Pembayaran Cabang')
@section('description', 'Lihat bukti dan riwayat keputusan pembayaran siswa dalam cabang Anda.')
@section('content')
    <div class="dpe-settings-page">
        <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Admin / Pembayaran</p><h2>{{ $charge->student?->full_name }}</h2><p>{{ $charge->charge_month->locale('id')->translatedFormat('F Y') }} · {{ $charge->branch?->name }}</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.payments.index', ['month' => $charge->charge_month->format('Y-m'), 'branch_id' => $charge->branch_id]) }}"><x-dashboard.icon name="back" />Kembali ke pembayaran</a></section>
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Status pembayaran</h2><p>Validasi pembayaran dilakukan oleh Super Admin.</p></div><x-dashboard.status :status="$state['code']" :label="$state['label']" /></header><div class="dpe-dashboard-card__body dpe-settings-detail">
            @if ($state['reason'])<p class="dpe-settings-note">{{ $state['reason'] }}</p>@endif
            <dl><div><dt>Nominal tagihan</dt><dd>{{ $charge->amount_idr === null ? 'Belum ditentukan' : 'Rp '.number_format((float) $charge->amount_idr, 2, ',', '.') }}</dd></div><div><dt>Jatuh tempo tagihan</dt><dd>{{ $charge->due_on?->format('d/m/Y') ?? 'Belum ditentukan' }}</dd></div><div><dt>Tanggal rekap cabang</dt><dd>Tanggal {{ $charge->branch?->payment_recap_day }} setiap bulan</dd></div></dl>
        </div></article>
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Bukti pembayaran</h2><p>Penolakan dan unggah ulang tetap tercatat dalam riwayat.</p></div></header>
            @if ($proofs->isEmpty())<x-dashboard.empty title="Belum ada bukti pembayaran" message="Siswa atau orang tua dapat mengunggah bukti melalui akun siswa." icon="wallet" />
            @else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-settings-table"><thead><tr><th>Dikirim</th><th>Status bukti</th><th>Pemeriksa / Waktu</th><th>Alasan keputusan</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($proofs as $proof)<tr><td>{{ $proof->submitted_at?->format('d/m/Y H:i') }}<small>{{ $proof->uploadedBy?->full_name }}</small></td><td><x-dashboard.status :status="$proof->status" :label="\App\Services\PortalPaymentService::LABELS[$proof->status] ?? 'Perlu Pemeriksaan Admin'" /></td><td>{{ $proof->reviewedBy?->full_name ?? 'Belum diperiksa' }}<small>{{ $proof->reviewed_at?->format('d/m/Y H:i') }}</small></td><td>{{ $proof->rejection_reason ?: '—' }}</td><td><div class="dpe-settings-actions">@if ($imageAvailable[$proof->id])<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.payments.image', $proof) }}" target="_blank" rel="noopener">Lihat bukti</a>@else<span>Gambar tidak tersedia</span>@endif @if (auth()->user()->role_code === 'superadmin')<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.payment-review.show', $proof) }}">Review pembayaran</a>@endif</div></td></tr>@endforeach
            </tbody></table></div>@endif
        </article>
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Riwayat aktivitas</h2><p>Waktu dan petugas mengikuti catatan sistem.</p></div></header>
            @if ($events->isEmpty())<x-dashboard.empty title="Belum ada riwayat aktivitas" message="Aktivitas unggah dan keputusan baru akan muncul di sini. Bukti lama tetap ditampilkan pada tabel di atas." icon="clock" />
            @else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-settings-table"><thead><tr><th>Waktu</th><th>Aktivitas</th><th>Pengguna</th><th>Keterangan</th></tr></thead><tbody>@foreach ($events as $event)<tr><td>{{ $event->occurred_at?->format('d/m/Y H:i') }}</td><td>{{ $event->event_type === 'payment_uploaded' ? 'Bukti diunggah' : 'Keputusan pembayaran' }}</td><td>{{ $event->actor?->full_name ?? 'Pengguna tidak tersedia' }}</td><td><x-dashboard.status :status="$event->after_snapshot['status'] ?? 'unknown'" :label="\App\Services\PortalPaymentService::LABELS[$event->after_snapshot['status'] ?? ''] ?? 'Status tercatat'" />@if ($event->reason)<small>{{ $event->reason }}</small>@endif</td></tr>@endforeach</tbody></table></div>@endif
        </article>
    </div>
@endsection
