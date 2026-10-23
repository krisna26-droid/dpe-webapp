@extends('layouts.dashboard')
@section('title', 'Periksa Pembayaran')
@section('heading', 'Periksa Pembayaran')
@section('description', 'Keputusan disimpan bersama petugas, waktu, dan riwayat perubahan.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    <div class="dpe-portal-actions dpe-portal-spaced"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.payment-review.index') }}">Kembali ke Review Pembayaran</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.monthly-charges.show', $paymentProof->charge) }}">Lihat Tagihan</a></div>
    <section class="dpe-portal-grid dpe-portal-spaced">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>{{ $paymentProof->charge->student?->full_name }}</h2><p>{{ $paymentProof->charge->charge_month->locale('id')->translatedFormat('F Y') }} · {{ $paymentProof->charge->branch?->name }}</p></div></header>
            <div class="dpe-dashboard-card__body">
                <p>Nominal: {{ $paymentProof->charge->amount_idr === null ? 'Belum ditetapkan' : 'Rp '.number_format((float) $paymentProof->charge->amount_idr, 2, ',', '.') }}</p>
                <p>Pengunggah: {{ $paymentProof->uploadedBy?->full_name }}</p>
                <p>Dikirim: {{ $paymentProof->submitted_at?->format('d M Y H:i') }}</p>
                @if ($imageAvailable)
                    <a href="{{ route('superadmin.payment-review.image', $paymentProof) }}" target="_blank" rel="noopener noreferrer"><img class="dpe-portal-proof-image" src="{{ route('superadmin.payment-review.image', $paymentProof) }}" alt="Bukti transfer siswa"></a>
                @else
                    <div class="dpe-dashboard-alert dpe-dashboard-alert--error">Gambar bukti tidak tersedia atau berubah. Bukti tidak dapat divalidasi sebelum gambar diperiksa.</div>
                @endif
            </div>
        </article>
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Keputusan pembayaran</h2><p>{{ \App\Services\PortalPaymentService::LABELS[$paymentProof->status] ?? 'Perlu Pemeriksaan Admin' }}</p></div></header>
            <div class="dpe-dashboard-card__body">
                @if ($paymentProof->rejection_reason)<p class="dpe-portal-text">Alasan keputusan terakhir: {{ $paymentProof->rejection_reason }}</p>@endif
                @if ($errors->has('expected_snapshot'))<p class="dpe-dashboard-alert dpe-dashboard-alert--error">{{ $errors->first('expected_snapshot') }} <a href="{{ route('superadmin.payment-review.show', $paymentProof) }}">Muat halaman terbaru</a></p>@endif
                @error('status')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                @if ($paymentProof->status !== 'approved')
                    <form method="POST" action="{{ route('superadmin.payment-review.review', $paymentProof) }}" data-confirm-form data-confirm-title="Validasi pembayaran ini?" data-confirm-message="Pembayaran untuk siswa dan bulan ini akan dinyatakan tervalidasi. Periksa gambar bukti sebelum melanjutkan." data-confirm-action="Validasi Pembayaran" class="dpe-portal-spaced">
                        @csrf
                        <input type="hidden" name="expected_snapshot" value="{{ $snapshot }}"><input type="hidden" name="status" value="approved">
                        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled(!$imageAvailable || $errors->has('expected_snapshot'))>Validasi Pembayaran</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('superadmin.payment-review.review', $paymentProof) }}" class="dpe-portal-settings-form" data-confirm-form data-confirm-title="{{ $paymentProof->status === 'approved' ? 'Batalkan validasi pembayaran?' : 'Tolak bukti ini?' }}" data-confirm-message="Alasan akan tersimpan dan ditampilkan kepada orang tua. Pembayaran yang ditolak tidak membuka laporan atau PDF." data-confirm-action="{{ $paymentProof->status === 'approved' ? 'Batalkan Validasi' : 'Tolak Bukti' }}">
                    @csrf
                    <input type="hidden" name="expected_snapshot" value="{{ $snapshot }}"><input type="hidden" name="status" value="rejected">
                    <div class="dpe-dashboard-field"><label for="rejection-reason">{{ $paymentProof->status === 'approved' ? 'Alasan pembatalan validasi' : 'Alasan penolakan' }}</label><textarea id="rejection-reason" name="rejection_reason" rows="4" maxlength="4000" required>{{ old('rejection_reason') }}</textarea>@error('rejection_reason')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                    <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit" @disabled($errors->has('expected_snapshot'))>{{ $paymentProof->status === 'approved' ? 'Batalkan Validasi' : 'Tolak Bukti' }}</button>
                </form>
            </div>
        </article>
    </section>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Riwayat pemeriksaan</h2><p>Riwayat baru mempertahankan keputusan sebelum dan sesudah perubahan.</p></div></header>
        <div class="dpe-dashboard-card__body">
            @forelse ($events as $event)
                <div class="dpe-portal-item"><strong>{{ $event->event_type === 'payment_uploaded' ? 'Bukti diunggah' : 'Keputusan pembayaran' }}</strong><small>{{ $event->actor?->full_name }} · {{ $event->occurred_at->format('d M Y H:i') }}</small><p>{{ \App\Services\PortalPaymentService::LABELS[$event->before_snapshot['status'] ?? 'empty'] ?? 'Status lama' }} → {{ \App\Services\PortalPaymentService::LABELS[$event->after_snapshot['status'] ?? 'pending'] ?? 'Status lama' }}</p>@if ($event->reason)<p class="dpe-portal-text">{{ $event->reason }}</p>@endif</div>
            @empty
                <p>Belum ada riwayat pemeriksaan baru. Riwayat sebelum pembaruan ini tidak direkonstruksi.</p>
            @endforelse
        </div>
    </article>
@endsection

