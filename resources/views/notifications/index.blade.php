@extends('layouts.dashboard')
@section('title', 'Notifikasi')
@section('heading', 'Notifikasi')
@section('description', 'Pemberitahuan dan pengingat sesuai akun, cabang, serta tanggung jawab Anda.')
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Aktivitas / Notifikasi</p><h2>Pantau hal yang perlu Anda tindak lanjuti.</h2><p>Riwayat pemberitahuan menampilkan 90 hari terakhir. Pengingat yang masih perlu ditindaklanjuti tetap tampil sampai kondisi selesai.</p></div>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route(auth()->user()->role_code.'.dashboard') }}"><x-dashboard.icon name="back" /><span>Kembali ke Dashboard</span></a>
    </section>
    <article class="dpe-dashboard-card dpe-operations-spaced">
        <header class="dpe-dashboard-card__head"><div><h2>Daftar notifikasi</h2><p>{{ $unread }} belum dibaca dari {{ $total }} pemberitahuan dalam cakupan Anda.</p></div></header>
        <div class="dpe-dashboard-card__body">
            <form method="GET" action="{{ route('inbox.index') }}" class="dpe-operations-filter">
                <div class="dpe-dashboard-field"><label for="inbox-type">Jenis notifikasi</label><select id="inbox-type" name="type">@foreach ($types as $code => $label)<option value="{{ $code }}" @selected($filters['type'] === $code)>{{ $label }}</option>@endforeach</select></div>
                <div class="dpe-dashboard-field"><label for="inbox-read">Status baca</label><select id="inbox-read" name="read"><option value="all" @selected($filters['read'] === 'all')>Semua status</option><option value="unread" @selected($filters['read'] === 'unread')>Belum dibaca</option><option value="read" @selected($filters['read'] === 'read')>Sudah dibaca</option></select></div>
                <div class="dpe-operations-actions"><button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Tampilkan</button><a href="{{ route('inbox.index') }}" class="dpe-dashboard-button dpe-dashboard-button--outline">Reset</a></div>
            </form>
        </div>
    </article>
    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>{{ $notifications->total() }} notifikasi sesuai filter</h2><p>Membaca notifikasi tidak menyelesaikan tugas atau mengubah status laporan maupun pembayaran.</p></div>
            @if ($notifications->getCollection()->contains('read', false))
                <form method="POST" action="{{ route('inbox.read-page') }}">@csrf
                    @foreach ($notifications as $item)
                        @if (! $item['read'])
                            <input type="hidden" name="keys[]" value="{{ $item['key'] }}">
                        @endif
                    @endforeach
                    <button class="dpe-dashboard-button dpe-dashboard-button--outline" type="submit">Tandai halaman ini dibaca</button>
                </form>
            @endif
        </header>
        <div class="dpe-dashboard-card__body">
            @forelse ($notifications as $item)
                <article class="dpe-inbox-item {{ $item['read'] ? '' : 'is-unread' }}">
                    <div class="dpe-inbox-copy"><div class="dpe-operations-actions"><span class="dpe-dashboard-badge">{{ $types[$item['type']] }}</span><span class="dpe-inbox-read-label">{{ $item['read'] ? 'Sudah dibaca' : 'Belum dibaca' }}</span></div>
                        <h3>{{ $item['title'] }}</h3><p>{{ $item['body'] }}</p><small>{{ $item['timeLabel'] }}</small>
                    </div>
                    <div class="dpe-operations-actions">
                        @if ($item['path'])<form method="POST" action="{{ route('inbox.open', $item['key']) }}">@csrf<button class="dpe-dashboard-button dpe-dashboard-button--soft" type="submit">Buka</button></form>@endif
                        @if (! $item['read'])<form method="POST" action="{{ route('inbox.read', $item['key']) }}">@csrf<button class="dpe-dashboard-button dpe-dashboard-button--outline" type="submit">Tandai dibaca</button></form>@endif
                    </div>
                </article>
            @empty
                <x-dashboard.empty title="Tidak ada notifikasi sesuai filter" message="Pemberitahuan akan muncul ketika ada aktivitas atau tugas dalam cakupan akun Anda." icon="bell" />
            @endforelse
        </div>
        <x-dashboard.pagination :paginator="$notifications" />
    </article>
@endsection
