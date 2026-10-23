@if ($readiness)
    <article class="dpe-dashboard-card dpe-progress-spaced" id="report-readiness">
        <header class="dpe-dashboard-card__head"><div><h2>Kelengkapan laporan</h2><p>Delapan kategori, ringkasan perkembangan, Challenges, Teacher’s Message, dan target periode diperiksa sebelum pengajuan/persetujuan.</p></div><span class="dpe-dashboard-badge {{ $readiness['ready'] ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">{{ $readiness['ready'] ? 'Siap ditinjau' : 'Perlu dilengkapi' }}</span></header>
        <div class="dpe-dashboard-card__body">
            @if ($readiness['errors'])<ul class="dpe-monthly-checklist">@foreach ($readiness['errors'] as $field => $message)@if ($field !== 'skills')<li>{{ $message }}</li>@endif
@endforeach</ul>@else<p>Isian wajib sudah lengkap.</p>@endif
            <p class="dpe-progress-help">{{ $readiness['videoCount'] }}{{ $readiness['targetReady'] ? ' / '.$monthlyReport->video_target : '' }} video tercatat. {{ $readiness['videoShortage'] > 0 ? 'Target belum tercapai. Kekurangan video tidak menghalangi pengajuan atau persetujuan.' : 'Jumlah video tidak menjadi syarat pengajuan atau persetujuan.' }}</p>
            @if (! $readiness['targetReady'])<p class="dpe-progress-help">Konfirmasi target oleh Admin berbeda dari jumlah video yang telah diunggah.</p>@endif
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($progressBase.'.edit', $monthlyReport) }}">{{ in_array($monthlyReport->status, ['draft', 'revision'], true) ? 'Lengkapi perkembangan & pesan' : 'Lihat perkembangan tersimpan' }}</a>
        </div>
    </article>
@endif
