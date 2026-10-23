<article class="dpe-dashboard-card dpe-operations-spaced">
    <header class="dpe-dashboard-card__head"><div><h2>Monitoring laporan · {{ $monitoring['monthLabel'] }}</h2><p>{{ $monitoring['summary']['video_missing'] }} periode perlu pemeriksaan video · {{ $monitoring['summary']['unpaid'] }} pembayaran belum tervalidasi.</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('monitoring.index', ['month' => $monitoring['filters']['month']]) }}">Buka monitoring</a></header>
    <div class="dpe-dashboard-card__body">@include('operations._summary', ['summary' => $monitoring['summary'], 'monthLabel' => $monitoring['monthLabel']])
        <p>Monitoring laporan memuat periode yang sudah disiapkan. <a href="{{ route(auth()->user()->role_code.'.student-progress.index', ['month' => $monitoring['filters']['month']]) }}">Buka Perkembangan &amp; Pesan</a> untuk melihat siswa dan menyiapkan periode yang diperlukan.</p>
    </div>
</article>
