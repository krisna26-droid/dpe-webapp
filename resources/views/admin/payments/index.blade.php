@extends('layouts.dashboard')
@section('title', 'Pembayaran Cabang')
@section('heading', 'Pembayaran Cabang')
@section('description', 'Pantau pembayaran siswa per bulan dan unduh rekap cabang.')
@section('content')
    <div class="dpe-settings-page">
        <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Admin / Pembayaran</p><h2>Rekap pembayaran siswa.</h2><p>Validasi dan penolakan bukti dilakukan Super Admin. Rekap ini mengikuti keputusan yang tersimpan.</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route(auth()->user()->role_code.'.dashboard') }}"><x-dashboard.icon name="back" />Kembali ke dashboard</a></section>
        <article class="dpe-dashboard-card"><div class="dpe-dashboard-card__body"><form method="GET" action="{{ route('admin.payments.index') }}" class="dpe-settings-filters">
            <div class="dpe-dashboard-field"><label for="branch-payment-month">Bulan pembayaran</label><input id="branch-payment-month" name="month" type="month" min="2000-01" max="2100-12" value="{{ $filters['month'] }}" required></div>
            <div class="dpe-dashboard-field"><label for="branch-payment-branch">Cabang</label><select id="branch-payment-branch" name="branch_id"><option value="">Semua cabang dalam akses</option>@foreach ($branches as $branch)<option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div class="dpe-dashboard-field"><label for="branch-payment-status">Status pembayaran</label><select id="branch-payment-status" name="status"><option value="all">Semua status</option>@foreach (\App\Services\PortalPaymentService::LABELS as $code => $label)<option value="{{ $code }}" @selected($filters['status'] === $code)>{{ $label }}</option>@endforeach</select></div>
            <div class="dpe-dashboard-field"><label for="branch-payment-q">Nama siswa</label><input id="branch-payment-q" name="q" type="search" maxlength="100" value="{{ $filters['q'] }}" placeholder="Cari nama siswa"></div>
            <div class="dpe-settings-filter-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Tampilkan</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.payments.index') }}">Reset</a></div>
        </form></div></article>
        <div class="dpe-settings-summary" aria-label="Ringkasan sesuai filter">@foreach ($summary as $code => $count)<x-dashboard.status :status="$code"><strong>{{ $count }}</strong> {{ \App\Services\PortalPaymentService::LABELS[$code] }}</x-dashboard.status>@endforeach</div>
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head dpe-settings-recap-head"><div><h2>Pembayaran · {{ \Carbon\CarbonImmutable::parse($filters['month'].'-01')->locale('id')->translatedFormat('F Y') }}</h2><p>{{ $total }} tagihan sesuai filter. Unduhan mencakup seluruh halaman hasil filter. Excel maksimal 3.000 baris; PDF maksimal 300 baris.</p></div><div class="dpe-settings-actions">
            @foreach (['xlsx' => 'Unduh Excel', 'pdf' => 'Unduh PDF'] as $format => $label)
                @if ($total > 0 && ($format === 'xlsx' || $total <= 300))
                    <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.payments.download', ['format' => $format] + $filters + ['expected_snapshot' => $snapshot]) }}">{{ $label }}</a>
                @endif
            @endforeach
        </div></header>
            @if ($pageRows->isEmpty())<x-dashboard.empty title="Belum ada tagihan sesuai filter" message="Pilih bulan lain atau periksa filter. Tagihan siswa disiapkan oleh Super Admin." icon="wallet" />
            @else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-settings-table"><thead><tr><th>Siswa / Bulan</th><th>Cabang</th><th>Status</th><th>Nominal / Jatuh tempo</th><th>Tanggal rekap</th><th>Aksi</th></tr></thead><tbody>
                @foreach ($pageRows as $row)<tr><td><strong>{{ $row['student'] }}</strong><small>{{ \Carbon\CarbonImmutable::parse($row['month'])->locale('id')->translatedFormat('F Y') }}</small></td><td>{{ $row['branch'] }}</td><td><x-dashboard.status :status="$row['status_code']" :label="$row['status']" /><small>{{ $row['proof_count'] }} bukti tersimpan</small></td><td>{{ $row['amount'] ?? 'Nominal belum ditentukan' }}<small>{{ $row['due'] ? 'Jatuh tempo '.\Carbon\CarbonImmutable::parse($row['due'])->format('d/m/Y') : 'Jatuh tempo belum ditentukan' }}</small></td><td>{{ \Carbon\CarbonImmutable::parse($row['recap'])->format('d/m/Y') }}</td><td><div class="dpe-settings-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('admin.payments.show', $row['id']) }}">Lihat detail</a></div></td></tr>@endforeach
            </tbody></table></div><x-dashboard.pagination :paginator="$pageRows" />@endif
        </article>
        <p class="dpe-settings-note">Tanggal rekap mengikuti pengaturan cabang. Tanggal tersebut tidak menetapkan jatuh tempo, denda, atau nominal pembayaran.</p>
    </div>
@endsection
