@extends('layouts.dashboard')
@section('title', 'Detail Pembayaran')
@section('heading', 'Detail Pembayaran')
@section('description', 'Unggah dan periksa bukti transfer untuk bulan ini.')
@push('head')
    @include('student.portal._head')
@endpush
@push('scripts')
    <script src="{{ asset('assets/dpe/portal-payment.js') }}?v={{ filemtime(public_path('assets/dpe/portal-payment.js')) }}" defer></script>
@endpush
@section('content')
    @include('student.portal._nav', ['back' => route('student.payments.index'), 'backLabel' => 'Kembali ke Pembayaran'])
    <article class="dpe-dashboard-card dpe-portal-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>{{ $monthlyCharge->charge_month->locale('id')->translatedFormat('F Y') }}</h2><p>{{ $monthlyCharge->branch?->name }}</p></div><x-dashboard.status :status="$state['code']" :label="$state['label']" /></header>
        <div class="dpe-dashboard-card__body">
            <p class="dpe-portal-payment-summary">{{ $monthlyCharge->amount_idr === null ? 'Nominal belum ditetapkan lembaga.' : 'Nominal: Rp '.number_format((float) $monthlyCharge->amount_idr, 2, ',', '.') }}</p>
            @if ($state['reason'])<p>{{ $state['reason'] }}</p>@endif
            @if ($student->status !== 'active')<p>Siswa nonaktif. Riwayat tetap tersedia; hubungi Admin sebelum mengunggah bukti baru.</p>@endif
            @if ($errors->has('expected_snapshot'))
                <p class="dpe-dashboard-alert dpe-dashboard-alert--error">{{ $errors->first('expected_snapshot') }} <a href="{{ route('student.payments.show', $monthlyCharge) }}">Muat halaman terbaru</a></p>
            @endif
            @if ($state['can_upload'] && $student->status === 'active')
                <form method="POST" enctype="multipart/form-data" action="{{ route('student.payments.upload', $monthlyCharge) }}" class="dpe-portal-upload-form" data-payment-upload data-payment-context-url="{{ route('student.payments.upload-context', $monthlyCharge) }}" data-payment-return-url="{{ route('student.payments.show', $monthlyCharge) }}" data-confirm-form data-confirm-title="Kirim bukti pembayaran?" data-confirm-message="Bukti untuk bulan ini akan dikirim ke Super Admin untuk divalidasi." data-confirm-action="Kirim Bukti">
                    @csrf
                    <input type="hidden" name="expected_snapshot" value="{{ $snapshot }}">
                    <div class="dpe-dashboard-field"><label for="payment-image">Bukti transfer</label><input id="payment-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" required><p class="dpe-dashboard-field__hint">JPG, PNG, atau WEBP, maksimal 5 MB dan 20 megapiksel.</p>@error('image')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>
                    <div class="dpe-portal-actions"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary" @disabled($errors->has('expected_snapshot'))>{{ $state['code'] === 'rejected' ? 'Unggah Ulang Bukti' : 'Kirim Bukti Pembayaran' }}</button></div>
                </form>
                <div class="dpe-portal-upload-feedback" data-payment-feedback role="status" aria-live="polite" hidden>
                    <p data-payment-message></p>
                    <a href="{{ route('student.payments.show', $monthlyCharge) }}">Muat halaman terbaru</a>
                </div>
            @elseif ($state['code'] === 'pending')
                <p>Bukti sedang menunggu validasi. Hasil keputusan akan tampil di halaman ini.</p>
            @elseif ($state['code'] === 'approved')
                <p>Pembayaran bulan ini sudah tervalidasi.</p>
            @endif
        </div>
    </article>
    <article class="dpe-dashboard-card dpe-portal-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Riwayat bukti</h2><p>Bukti yang ditolak tetap tersimpan setelah unggah ulang.</p></div></header>
        <div class="dpe-dashboard-card__body">
            @forelse ($state['proofs'] as $proof)
                <div class="dpe-portal-item">
                    <strong>{{ $proof->submitted_at?->format('d M Y H:i') }}</strong>
                    <p>{{ \App\Services\PortalPaymentService::LABELS[$proof->status] ?? 'Perlu Pemeriksaan Admin' }}</p>
                    @if ($proof->status === 'rejected')<p class="dpe-portal-text">Alasan: {{ $proof->rejection_reason ?: 'Hubungi Admin untuk keterangan.' }}</p>@endif
                    <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('student.payments.image', $proof) }}" target="_blank" rel="noopener noreferrer">Buka Bukti</a>
                </div>
            @empty
                <p>Belum ada bukti untuk bulan ini.</p>
            @endforelse
        </div>
    </article>
    @if ($events->isNotEmpty())
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head"><div><h2>Riwayat proses pembayaran</h2><p>Keputusan dan perubahan yang tercatat.</p></div></header>
            <div class="dpe-dashboard-card__body">
                @foreach ($events as $event)
                    <div class="dpe-portal-item"><strong>{{ $event->event_type === 'payment_uploaded' ? 'Bukti diunggah' : 'Keputusan Super Admin' }}</strong><small>{{ $event->occurred_at->format('d M Y H:i') }} · {{ \App\Services\PortalPaymentService::LABELS[$event->after_snapshot['status'] ?? 'pending'] ?? 'Perlu Pemeriksaan Admin' }}</small>@if ($event->reason)<p class="dpe-portal-text">{{ $event->reason }}</p>@endif</div>
                @endforeach
            </div>
        </article>
    @endif
@endsection

