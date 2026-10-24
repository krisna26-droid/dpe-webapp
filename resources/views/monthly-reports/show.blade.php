@php
    $stale = $errors->has('expected_snapshot');
    $public = $display['public'];
    $canSubmit = $teacher && in_array($monthlyReport->status, ['draft', 'revision'], true);
    $canReview = ! $teacher && $monthlyReport->status === 'submitted';
    $canReopen = $teacher && $monthlyReport->status === 'approved';
    $reopened = $monthlyReport->status === 'draft' && $monthlyReport->statusEvents()->where('from_status', 'approved')->where('to_status', 'draft')->exists();
    $lastRevision = $monthlyReport->statusEvents()->where('to_status', 'revision')->orderByDesc('occurred_at')->orderByDesc('id')->first();
@endphp
@extends('layouts.dashboard')
@section('title', 'Detail Laporan Bulanan')
@section('heading', 'Laporan Bulanan')
@section('description', 'Periksa isi laporan, keputusan tinjauan, dan riwayat status siswa.')
@push('head')
    <link rel="stylesheet" href="{{ asset('assets/dpe/student-progress.css') }}?v={{ filemtime(public_path('assets/dpe/student-progress.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/dpe/monthly-reports.css') }}?v={{ filemtime(public_path('assets/dpe/monthly-reports.css')) }}">
@endpush
@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Laporan / {{ $monthlyReport->report_month->locale('id')->translatedFormat('F Y') }}</p><h2>{{ $public['student_name'] ?: $monthlyReport->student->full_name }}</h2><p>{{ $public['branch_name'] ?: $monthlyReport->branch?->name }} · {{ $public['teacher_name'] ?: $monthlyReport->teacher?->user?->full_name }}</p></div>
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index', ['month' => $monthlyReport->report_month->format('Y-m'), 'status' => 'all']) }}"><x-dashboard.icon name="back" />Kembali ke laporan bulanan</a>
    </section>
    <section class="dpe-dashboard-grid dpe-progress-metrics" aria-label="Status laporan bulanan">
        <x-dashboard.metric-card :status="$monthlyReport->status" label="Status laporan" :value="$statusLabels[$monthlyReport->status] ?? 'Perlu diperiksa'" :meta="$monthlyReport->status === 'approved' ? 'Isi persetujuan dikunci' : 'Satu siswa · satu bulan'" icon="report" />
        <x-dashboard.metric-card label="Video belajar" :value="$display['videosKnown'] ? count($public['videos']).' / '.$public['video_target'] : 'Belum tersimpan'" :meta="$display['videosKnown'] ? 'Kekurangan video tidak memblokir laporan' : 'Salinan persetujuan lama tidak memuat video'" tone="green" icon="video" />
        <x-dashboard.metric-card label="Tenggat laporan" :value="$monthlyReport->due_on?->format('d M Y') ?: '—'" meta="Laporan lewat tenggat tetap dapat diproses" tone="purple" icon="clock" />
    </section>
    @if ($stale)<div class="dpe-progress-notice" role="alert"><x-dashboard.icon name="warning" /><div><p>{{ $errors->first('expected_snapshot') }}</p><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.show', $monthlyReport) }}">Muat data terbaru</a></div></div>@endif
    @if ($monthlyReport->status === 'revision')<div class="dpe-progress-notice" role="status"><x-dashboard.icon name="warning" /><div><strong>Perbaikan dari Admin</strong><p class="dpe-monthly-text">{{ $lastRevision?->comment ?: 'Periksa riwayat tinjauan atau hubungi Admin untuk alasan revisi.' }}</p><p>Lengkapi perkembangan dan pesan, simpan draf, lalu ajukan kembali laporan ini.</p></div></div>@endif
    @if ($monthlyReport->status === 'submitted')<div class="dpe-progress-notice"><x-dashboard.icon name="info" /><p>Laporan menunggu tinjauan Admin. Isi perkembangan, video, dan target terkunci sampai laporan dikembalikan untuk revisi.</p></div>@endif
    @if ($monthlyReport->status === 'approved')
        <div class="dpe-progress-notice"><x-dashboard.icon name="check" /><div>
            <x-dashboard.approval :user="$monthlyReport->approvedBy" :at="$monthlyReport->approved_at" :timezone="$monthlyReport->branch?->timezone_name" />
            @if ($display['frozen'])<p>Isi di bawah mengikuti salinan persetujuan.</p>@endif
        </div></div>
    @endif
    @if ($display['notice'])<div class="dpe-progress-notice"><x-dashboard.icon name="info" /><p>{{ $display['notice'] }}</p></div>@endif
    @include('monthly-reports._readiness')
    @unless($action)
        <article class="dpe-dashboard-card dpe-progress-spaced">
            <header class="dpe-dashboard-card__head"><div><h2>Bagikan laporan bulanan</h2><p>Periode {{ $monthlyReport->report_month->locale('id')->translatedFormat('F Y') }}. Pengiriman tersedia setelah laporan disetujui dan pembayaran bulan ini tervalidasi.</p></div></header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-form-actions">
                    @if($deliveryState['ready'])<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.pdf.preview', $monthlyReport) }}" target="_blank" rel="noopener noreferrer">Preview PDF bulanan</a>
                    @else<button class="dpe-dashboard-button dpe-dashboard-button--outline" type="button" disabled>Preview PDF bulanan</button>@endif
                    <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($routeBase.'.whatsapp.index', $monthlyReport) }}">WhatsApp &amp; riwayat</a>
                </div>
                <p class="dpe-progress-help">Pilih kirim tautan atau file PDF pada halaman WhatsApp. Untuk pilihan PDF, file disiapkan ketika Teacher menekan Siapkan pesan.</p>
            </div>
        </article>
    @endunless
    @if ($action)
        <x-dashboard.form-panel id="monthly-confirmation" :title="['submit' => 'Ajukan laporan kepada Admin?', 'approve' => 'Setujui laporan bulanan?', 'revision' => 'Kembalikan laporan untuk revisi?', 'reopen' => 'Mulai revisi laporan bulanan?'][$action]" :description="$action === 'reopen' ? 'Isi laporan tetap tersimpan. Setelah revisi dibuka, perbaiki dan ajukan ulang untuk persetujuan Admin. Akses orang tua dan berbagi laporan menunggu persetujuan ulang; PDF lama tetap tersimpan.' : 'Periksa isi laporan di bawah. Tindakan dilakukan setelah Anda menekan tombol konfirmasi.'" :close-url="route($routeBase.'.show', $monthlyReport)">
            <form method="POST" action="{{ route($routeBase.'.'.$action, $monthlyReport) }}" class="dpe-dashboard-form" data-monthly-action="{{ $action }}">
                @csrf
                <input type="hidden" name="expected_snapshot" value="{{ old('expected_snapshot', $expectedSnapshot) }}">
                @if (in_array($action, ['revision', 'reopen'], true))<div class="dpe-dashboard-field"><label for="monthly-comment">{{ $action === 'reopen' ? 'Alasan revisi baru' : 'Alasan revisi' }}</label><textarea id="monthly-comment" name="comment" maxlength="255" rows="4" required @readonly($stale)>{{ old('comment') }}</textarea><p class="dpe-dashboard-field__hint">{{ $action === 'reopen' ? 'Jelaskan bagian yang perlu diperbaiki. Alasan dan petugas dicatat pada riwayat laporan.' : 'Tulis perbaikan yang diperlukan. Alasan ini akan terlihat oleh Teacher.' }}</p>@error('comment')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror</div>@endif
                <div class="dpe-dashboard-form-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.show', $monthlyReport) }}">Batal</a><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($stale || (in_array($action, ['submit', 'approve'], true) && ! ($readiness['ready'] ?? false)))>{{ ['submit' => 'Ya, ajukan laporan', 'approve' => 'Ya, setujui laporan', 'revision' => 'Kirim permintaan revisi', 'reopen' => 'Ya, mulai revisi baru'][$action] }}</button></div>
            </form>
        </x-dashboard.form-panel>
    @else
        @if ($canSubmit || $canReview || $canReopen)<article class="dpe-dashboard-card dpe-progress-spaced"><div class="dpe-dashboard-card__body dpe-monthly-actions"><p>{{ $canReopen ? 'Perlu perbaikan setelah disetujui? Mulai revisi baru, perbaiki isi, lalu ajukan ulang kepada Admin.' : ($canSubmit ? 'Pengajuan memakai isian tersimpan. Periksa semua bagian laporan sebelum melanjutkan.' : 'Persetujuan mengunci salinan isi. Jika perlu perbaikan, kembalikan laporan dengan alasan.') }}</p><div class="dpe-monthly-action-buttons">
            @if ($canReopen && ! $stale)<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.confirm', ['monthlyReport' => $monthlyReport, 'action' => 'reopen']).'#monthly-confirmation' }}">Mulai revisi baru</a>@endif
            @if ($canSubmit && ! $stale)<a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($routeBase.'.confirm', ['monthlyReport' => $monthlyReport, 'action' => 'submit']) }}">{{ $monthlyReport->status === 'revision' || $reopened ? 'Ajukan ulang laporan' : 'Ajukan laporan' }}</a>@endif
            @if ($canReview && ! $stale)<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.confirm', ['monthlyReport' => $monthlyReport, 'action' => 'revision']) }}">Minta revisi</a>
                <x-dashboard.report-approval
                    :confirm-url="route($routeBase.'.confirm', ['monthlyReport' => $monthlyReport, 'action' => 'approve'])"
                    :approve-url="route($routeBase.'.approve', $monthlyReport)"
                    :expected-snapshot="$expectedSnapshot"
                    :disabled="! ($readiness['ready'] ?? false)"
                    label="Setujui laporan"
                    confirm-label="Ya, setujui laporan"
                />
            @endif
        </div></div></article>@endif
    @endif
    <div class="dpe-monthly-public {{ $action ? 'dpe-monthly-after-confirm' : '' }}" data-monthly-public>
        <article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Delapan kategori perkembangan</h2><p>{{ $monthlyReport->report_month->locale('id')->translatedFormat('F Y') }} · Ringkasan untuk orang tua</p></div></header><div class="dpe-dashboard-card__body"><div class="dpe-progress-skills">
            @foreach ($public['skills'] as $skill)<section class="dpe-monthly-skill"><h3>{{ $skill['name'] }}</h3><x-dashboard.status :status="$skill['trend'] ?: 'unrecorded'" :label="$skill['trend'] ?: 'Belum dinilai'" /><p class="dpe-monthly-text">{{ $skill['description'] ?: 'Deskripsi belum diisi.' }}</p></section>@endforeach
        </div></div></article>
        <article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Ringkasan, Challenges, dan pesan</h2><p>Isian ini terpisah dari catatan internal petugas.</p></div></header><div class="dpe-dashboard-card__body dpe-monthly-sections">@foreach (array_slice(\App\Services\StudentProgressService::CONTENT, 0, 3, true) as $field => $label)<section><h3>{{ $label }}</h3><p class="dpe-monthly-text">{{ $public[$field] ?: 'Belum diisi.' }}</p></section>@endforeach</div></article>
        <article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Video belajar</h2><p>{{ $display['frozen'] ? 'Tautan yang tersimpan pada persetujuan ini.' : 'Tautan dengan tanggal dan URL valid pada bulan laporan.' }}</p></div></header>
            @if (empty($public['videos']))<x-dashboard.empty title="Belum ada tautan video dalam laporan ini" message="Jumlah video tidak menjadi syarat pengajuan atau persetujuan." icon="video" />@else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-monthly-video-table"><thead><tr><th>Tanggal</th><th>Topik / Keterangan</th><th>Teacher</th><th>Aksi</th></tr></thead><tbody>@foreach ($public['videos'] as $video)<tr><td>{{ $video['video_on'] ?: '—' }}</td><td><strong>{{ $video['topic'] ?: 'Video belajar' }}</strong><small class="dpe-monthly-text">{{ $video['description'] }}</small></td><td>{{ $video['teacher_name'] ?: '—' }}</td><td>@if ($video['video_url'])<a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ $video['video_url'] }}" target="_blank" rel="noopener noreferrer">Buka video</a>@else<small>Tautan tidak tersedia</small>@endif</td></tr>@endforeach</tbody></table></div>@endif
        </article>
    </div>
    <article class="dpe-dashboard-card dpe-progress-private dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Catatan internal petugas</h2><p>Bagian ini hanya untuk Teacher pemilik, Admin cabang, dan Super Admin.</p></div><span class="dpe-dashboard-badge">Internal</span></header><div class="dpe-dashboard-card__body"><p class="dpe-monthly-text">{{ $display['internal'] ?: 'Tidak ada catatan internal bulanan.' }}</p></div></article>
    <article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Absensi siswa — internal</h2><p>Rekap terkini bulan ini pada cabang laporan. Koreksi absensi mengikuti menu Absensi Siswa.</p></div><span class="dpe-dashboard-badge">{{ $attendance['attended_count'] }} hadir / {{ $attendance['meeting_count'] }} pertemuan berlangsung</span></header><div class="dpe-dashboard-card__body"><p>Hadir {{ $attendance['present'] }} · Terlambat {{ $attendance['late'] }} · Tidak Hadir {{ $attendance['absent'] }} · Sakit {{ $attendance['sick'] }} · Izin {{ $attendance['permission'] }} · Belum dicatat {{ $attendance['unrecorded'] }} · Jadwal ulang {{ $attendance['rescheduled'] }}</p><p class="dpe-progress-help">Sesi mendatang, dibatalkan, digantikan, atau sebelum tanggal mulai les tidak menjadi pertemuan berlangsung. Absensi belum lengkap tidak memblokir laporan.</p></div></article>
    <article class="dpe-dashboard-card" id="report-history"><header class="dpe-dashboard-card__head"><div><h2>Riwayat status laporan</h2><p>Pengajuan dan keputusan dicatat bersama petugas serta alasan revisi.</p></div><span class="dpe-dashboard-badge">{{ $history->total() }} tindakan</span></header>
        @if ($history->isEmpty())<x-dashboard.empty title="Belum ada perubahan status" message="Riwayat muncul setelah Teacher mengajukan atau Admin memproses laporan." icon="clock" />@else<div class="dpe-monthly-history">@foreach ($history as $event)<article><strong>{{ $statusLabels[$event->from_status] ?? ($event->from_status ?: 'Awal') }} → {{ $statusLabels[$event->to_status] ?? $event->to_status }}</strong><small>{{ $event->actor?->full_name ?: 'Petugas tidak tersedia' }} · {{ $event->occurred_at->format('d M Y H:i') }}</small>@if ($event->comment)<p class="dpe-monthly-text">{{ $event->comment }}</p>@endif</article>@endforeach</div><x-dashboard.pagination :paginator="$history" />@endif
    </article>
@endsection
