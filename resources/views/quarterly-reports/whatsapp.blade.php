@extends('layouts.dashboard')
@section('title', 'Bagikan Rapor melalui WhatsApp')
@section('heading', 'Bagikan Rapor')
@section('description', 'Siapkan pesan, periksa penerima, lalu kirim melalui WhatsApp Anda.')
@push('head')
@include('quarterly-reports._head')
<link rel="stylesheet" href="{{ asset('assets/dpe/report-whatsapp.css') }}?v={{ filemtime(public_path('assets/dpe/report-whatsapp.css')) }}">
@endpush
@section('content')
@php($waZone = in_array($report->branch->timezone_name, timezone_identifiers_list(), true) ? $report->branch->timezone_name : config('app.timezone'))
@php($contact = $share?->contact_snapshot ?? $context['contact'])
@php($reasons = $operationReasons ?? $context['reasons'])
@php($enabled = $canSend && !$reasons && (!$share || $share->actor_user_id === auth()->id()))
@php($outboundEnabled = $enabled && !($expired ?? false))
<section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">{{ $reportLabel ?? 'Rapor tiga bulan' }}</p><h2>{{ $report->student->full_name }}</h2><p>{{ $context['contact']['period'] }}</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.show', $report) }}">Kembali ke Rapor</a></section>
<div class="dpe-wa-context">
    <article class="dpe-dashboard-card"><div class="dpe-dashboard-card__body"><p class="dpe-dashboard-kicker">Teacher pengelola</p><h3>{{ $contact['teacher_name'] }}</h3><p>Kontak terdaftar: {{ $contact['teacher_phone'] ?: 'Belum tersedia' }}</p><p class="dpe-wa-hint">Pengirim mengikuti akun WhatsApp aktif di perangkat Anda. Nomor terdaftar tidak menautkan akun WhatsApp.</p></div></article>
    <article class="dpe-dashboard-card"><div class="dpe-dashboard-card__body"><p class="dpe-dashboard-kicker">Penerima utama</p><h3>{{ $contact['recipient_name'] ?: 'Belum ditetapkan' }}</h3><p>{{ $contact['relationship'] ?: 'Kontak penerima rapor' }} · {{ $contact['recipient_phone'] ?: 'Nomor perlu diperiksa' }}</p><p class="dpe-wa-hint">Perbaikan nama/nomor dilakukan melalui data wali dan penerima rapor siswa oleh Admin yang berwenang.</p></div></article>
</div>
@if($reasons)<div class="dpe-final-notice dpe-wa-notice" role="status"><strong>Berbagi belum dapat dilanjutkan</strong><ul>@foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul></div>@endif
@if(!$canSend)<div class="dpe-final-notice dpe-wa-notice">Admin Cabang dapat memeriksa riwayat. Teacher pemilik rapor melakukan pengiriman; Super Admin dapat membantu dengan identitas petugas tercatat.</div>@endif
@if(!$share)
<form method="POST" action="{{ route($routeBase.'.whatsapp.preview', $report) }}" class="dpe-wa-options">
    @csrf
    <div class="dpe-dashboard-field"><label for="wa-method">Cara berbagi</label><select id="wa-method" name="method">@foreach($methods as $value => $label)<option value="{{ $value }}" @selected($options['method'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <div class="dpe-dashboard-field"><label for="wa-salutation">Sapaan penerima</label><select id="wa-salutation" name="salutation">@foreach($salutations as $value => $label)<option value="{{ $value }}" @selected($options['salutation'] === $value)>{{ $label }}</option>@endforeach</select></div>
    <button type="submit" class="dpe-dashboard-button dpe-dashboard-button--outline">Perbarui pratinjau</button>
</form>
<p class="dpe-wa-hint dpe-wa-notice">Pilihan pratinjau diingat untuk akun Anda pada rapor ini selama sesi login aktif.</p>
@else
<div class="dpe-wa-notice"><x-dashboard.status :status="$share->confirmed_at ? 'approved' : 'pending'" :label="$shareStatuses[$share->status] ?? 'Periksa riwayat'" /><p>{{ $methods[$share->method] }} · Disiapkan {{ $share->prepared_at->timezone($waZone)->format('d M Y H:i') }} · {{ $contact['operator_name'] }}</p></div>
@endif
<article class="dpe-dashboard-card dpe-wa-notice"><header class="dpe-dashboard-card__head"><div><h2>Pratinjau pesan</h2><p>{{ $options['method'] === 'link' ? 'Tautan membuka portal anak. Penerima tetap perlu login.' : 'Template pesan disiapkan. File PDF dilampirkan manual di WhatsApp sebelum dikirim.' }}</p></div></header><div class="dpe-dashboard-card__body">
    <label for="wa-message" class="dpe-wa-label">Pesan yang disiapkan</label><textarea id="wa-message" class="dpe-wa-message" rows="17" readonly>{{ $message }}</textarea>
    @if(!$share)
    <form method="POST" action="{{ route($routeBase.'.whatsapp.prepare', $report) }}" class="dpe-wa-actions" data-confirm-form data-confirm-title="Siapkan pesan WhatsApp?" data-confirm-message="Pastikan siswa, periode, penerima, dan sapaan sudah benar. Pesan belum dikirim pada langkah ini.">
        @csrf<input type="hidden" name="method" value="{{ $options['method'] }}"><input type="hidden" name="salutation" value="{{ $options['salutation'] }}"><input type="hidden" name="request_key" value="{{ $requestKey }}"><input type="hidden" name="expected_snapshot" value="{{ $expectedSnapshot }}">
        <button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary" @disabled(!$enabled)>Siapkan pesan</button>
    </form>
    @else
    <div class="dpe-wa-actions">
        @if($share->method === 'pdf')<form method="POST" action="{{ route($routeBase.'.whatsapp.download', [$report, $share]) }}">@csrf<button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled(!$outboundEnabled)>1. Unduh PDF</button></form>@endif
        <form method="POST" action="{{ route($routeBase.'.whatsapp.open', [$report, $share]) }}" target="_blank" rel="noopener noreferrer">@csrf<button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled(!$outboundEnabled)>{{ $share->method === 'pdf' ? '2. Buka WhatsApp' : 'Buka WhatsApp' }}</button></form>
        <button type="button" class="dpe-dashboard-button dpe-dashboard-button--outline" data-wa-copy="wa-message" @disabled(!$outboundEnabled)>Salin pesan</button>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.whatsapp.index', [$report, 'method' => $share->method, 'salutation' => $share->salutation]) }}">Siapkan pesan baru</a>
    </div>
    @if($expired ?? false)<p class="dpe-final-notice">Pesan disiapkan lebih dari 30 menit lalu. Siapkan pesan baru untuk membuka WhatsApp atau mengunduh PDF. Pengiriman yang sudah Anda lakukan tetap dapat dikonfirmasi.</p>@endif
    <p data-wa-copy-status role="status" class="dpe-wa-hint"></p>
    <p class="dpe-wa-hint">Periksa akun WhatsApp aktif, nomor tujuan, dan pesan sebelum menekan Kirim. @if($share->method === 'pdf')Lampirkan PDF yang baru diunduh. @endif Membuka WhatsApp belum membuktikan pesan terkirim.</p>
    @if(!$share->confirmed_at && $canSend && $share->actor_user_id === auth()->id())
    <form method="POST" action="{{ route($routeBase.'.whatsapp.confirm', [$report, $share]) }}" class="dpe-wa-confirm" data-confirm-form data-confirm-title="Simpan konfirmasi pengiriman?" data-confirm-message="Konfirmasi ini berdasarkan pengakuan Anda setelah mengirim di WhatsApp; bukan status otomatis dari WhatsApp.">
        @csrf<label><input type="checkbox" name="sent_confirmed" value="1" required> Saya sudah mengirim pesan @if($share->method === 'pdf')beserta PDF @endif ke penerima yang tertera melalui WhatsApp.</label>
        <button type="submit" class="dpe-dashboard-button dpe-dashboard-button--outline" @disabled(!$enabled)>Saya sudah mengirim</button>
        <p class="dpe-wa-hint">Setelah mengunduh atau membuka WhatsApp, muat ulang halaman ini untuk melihat waktu tindakan pada riwayat.</p>
    </form>
    @endif
    @endif
</div></article>
@if($options['method'] === 'link')<p class="dpe-wa-hint dpe-wa-notice">Pastikan alamat DPE dapat dijangkau penerima. Alamat localhost atau 127.0.0.1 hanya bekerja pada perangkat yang menjalankan aplikasi.</p>@endif
<article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Riwayat berbagi</h2><p>Waktu buka dan konfirmasi merupakan catatan aplikasi. Status diterima/dibaca tidak tersedia.</p></div></header><div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table"><thead><tr><th>Disiapkan</th><th>Metode / PDF</th><th>Penerima</th><th>Petugas</th><th>Status</th><th>Waktu tindakan</th></tr></thead><tbody>
@forelse($shares as $record)<tr><td>{{ $record->prepared_at->timezone($waZone)->format('d M Y H:i') }}<small><a href="{{ route($routeBase.'.whatsapp.show', [$report, $record]) }}">Lihat pesan</a></small></td><td>{{ $methods[$record->method] ?? 'Riwayat' }}<small>@if($record->method === 'pdf' || !isset($reportLabel))PDF v{{ $record->contact_snapshot['pdf_version'] ?? '—' }} · @endif{{ $record->contact_snapshot['approval_label'] ?? ('Revisi '.($record->contact_snapshot['approval_revision'] ?? '—')) }}</small></td><td>{{ $record->contact_snapshot['recipient_name'] }}<small>{{ $record->contact_snapshot['recipient_phone'] }}</small></td><td>{{ $record->contact_snapshot['operator_name'] }}</td><td><x-dashboard.status :status="$record->confirmed_at ? 'approved' : 'pending'" :label="$shareStatuses[$record->status] ?? 'Periksa riwayat'" /></td><td><small>Buka: {{ $record->opened_at?->timezone($waZone)->format('d M Y H:i') ?: 'Belum dicatat' }}</small><small>Unduh: {{ $record->downloaded_at?->timezone($waZone)->format('d M Y H:i') ?: 'Belum dicatat' }}</small><small>Konfirmasi: {{ $record->confirmed_at?->timezone($waZone)->format('d M Y H:i') ?: 'Belum dicatat' }}</small></td></tr>
@empty<tr><td colspan="6">Belum ada riwayat berbagi untuk rapor ini.</td></tr>@endforelse
</tbody></table></div><x-dashboard.pagination :paginator="$shares" /></article>
@endsection
@push('scripts')<script src="{{ asset('assets/dpe/report-whatsapp.js') }}?v={{ filemtime(public_path('assets/dpe/report-whatsapp.js')) }}" defer></script>@endpush
