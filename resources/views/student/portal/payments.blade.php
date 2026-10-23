@extends('layouts.dashboard')
@section('title', 'Pembayaran')
@section('heading', 'Pembayaran')
@section('description', 'Status pembayaran bulanan dan riwayat bukti transfer.')
@push('head')
    @include('student.portal._head')
@endpush
@section('content')
    @include('student.portal._nav')
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Pembayaran bulanan</h2><p>Pilih periode yang disiapkan lembaga, lalu unggah bukti transfer.</p></div></header>
        @if ($rows->isEmpty())
            <x-dashboard.empty title="Belum ada periode pembayaran" message="Hubungi Admin agar periode pembayaran anak disiapkan melalui Tagihan Bulanan. Sistem tidak menentukan biaya kursus secara otomatis." icon="wallet" />
        @else
            <div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-portal-table"><thead><tr><th>Bulan / Cabang</th><th>Status</th><th>Nominal / Jatuh tempo</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($rows as $row)
                    <tr><td><strong>{{ $row['charge']->charge_month->locale('id')->translatedFormat('F Y') }}</strong><small>{{ $row['charge']->branch?->name }}</small></td><td><x-dashboard.status :status="$row['state']['code']" :label="$row['state']['label']" /></td><td>{{ $row['charge']->amount_idr === null ? 'Nominal belum ditetapkan' : 'Rp '.number_format((float) $row['charge']->amount_idr, 2, ',', '.') }}<small>{{ $row['charge']->due_on ? 'Jatuh tempo '.$row['charge']->due_on->format('d M Y') : 'Jatuh tempo belum ditetapkan' }}</small></td><td><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('student.payments.show', $row['charge']) }}">{{ $row['state']['can_upload'] ? 'Unggah Bukti' : 'Lihat Pembayaran' }}</a></td></tr>
                @endforeach
            </tbody></table></div>
            <x-dashboard.pagination :paginator="$charges" />
        @endif
    </article>
@endsection

