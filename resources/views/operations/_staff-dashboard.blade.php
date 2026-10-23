<section class="dpe-dashboard-page-header">
    <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">{{ $actor->role_code === 'teacher' ? 'Ruang kerja pengajar' : 'Overview pusat' }}</p><h2>Selamat datang, {{ $actor->full_name }}.</h2><p>Pantau tugas laporan, jadwal hari ini, dan notifikasi yang perlu ditindaklanjuti.</p></div>
    <div class="dpe-operations-actions"><a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('monitoring.index') }}">Monitoring Laporan</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('inbox.index') }}">Notifikasi</a></div>
</section>
<section class="dpe-dashboard-grid dpe-dashboard-grid--metrics dpe-operations-spaced" aria-label="Ringkasan aktivitas">
    @if ($actor->role_code === 'superadmin')
        <x-dashboard.metric-card label="Cabang aktif" :value="$activeBranches" meta="Seluruh cabang" icon="building" />
        <x-dashboard.metric-card label="Pengguna aktif" :value="$activeUsers" meta="Seluruh peran" icon="users" tone="green" />
    @else
        <x-dashboard.metric-card label="Siswa binaan bulan ini" :value="$myStudents" meta="Penugasan, kelas, atau laporan dalam cakupan Anda" icon="student" />
        <x-dashboard.metric-card label="Periode laporan bulan ini" :value="$monitoring['summary']['total']" meta="Periode yang sudah disiapkan" icon="report" tone="green" />
    @endif
    <x-dashboard.metric-card label="Sesi hari ini" :value="$todaySessions->count()" :meta="today()->format('d/m/Y')" icon="calendar" tone="orange" />
    <x-dashboard.metric-card label="Laporan diajukan bulan ini" :value="$monitoring['summary']['submitted']" meta="Menunggu tinjauan Admin" icon="report" tone="purple" />
</section>
@if ($actor->role_code === 'superadmin' && ($showDashboardOverview ?? false))
    @include('superadmin._dashboard-overview', ['overview' => $dashboardOverview])
@endif
<article class="dpe-dashboard-card">
    <header class="dpe-dashboard-card__head"><div><h2>Jadwal hari ini</h2><p>Sesi yang dibatalkan atau sudah diganti jadwalnya tidak ditampilkan.</p></div></header>
    <div class="dpe-dashboard-card__body">
        @forelse ($todaySessions as $session)
            <div class="dpe-inbox-item"><div class="dpe-inbox-copy"><h3>{{ $session->topic ?: 'Sesi belajar' }}</h3><p>{{ $session->planned_start_at?->format('H:i') }}–{{ $session->planned_end_at?->format('H:i') }} · {{ $session->branch?->name }} · {{ $session->teacher?->user?->full_name }}</p></div><x-dashboard.status :status="$session->status" :label="['scheduled' => 'Terjadwal', 'ongoing' => 'Berlangsung', 'in_progress' => 'Berlangsung', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'][$session->status] ?? 'Periksa sesi'" /></div>
        @empty<x-dashboard.empty title="Belum ada sesi hari ini" message="Jadwal tampil setelah sesi disiapkan sesuai penugasan." icon="calendar" />@endforelse
    </div>
</article>
