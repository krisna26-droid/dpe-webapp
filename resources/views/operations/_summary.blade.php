<section class="dpe-dashboard-grid dpe-dashboard-grid--metrics dpe-operations-spaced" aria-label="Ringkasan laporan sesuai filter">
    <x-dashboard.metric-card label="Laporan bulanan" :value="$summary['total']" :meta="$monthLabel.' · sesuai filter'" icon="report" />
    <x-dashboard.metric-card label="Disetujui" :value="$summary['approved']" meta="Laporan bulanan Approved" tone="green" icon="check" />
    <x-dashboard.metric-card label="Belum disetujui" :value="$summary['pending']" :meta="$summary['submitted'].' diajukan · '.$summary['revision'].' perlu revisi · '.$summary['draft'].' draf'" tone="purple" icon="report" />
    <x-dashboard.metric-card label="Tenggat terlewati" :value="$summary['overdue']" meta="Laporan bulanan belum Approved" tone="orange" icon="clock" />
</section>
