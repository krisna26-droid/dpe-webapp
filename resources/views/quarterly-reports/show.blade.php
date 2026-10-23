
@extends('layouts.dashboard')
@section('title', 'Periksa Rapor Tiga Bulan')
@section('heading', 'Periksa Rapor Tiga Bulan')
@section('description', 'Periksa isi, persetujuan, kelengkapan periode, dan versi PDF.')
@push('head')
@include('quarterly-reports._head')
@endpush
@section('content')
@php($stale = old('expected_snapshot') && old('expected_snapshot') !== $expectedSnapshot)
@php($previewReady = $readiness['completed'] === 4 && filled($report->parent_note))
@php($currentFile = $files->firstWhere('approval_revision', $report->revision_number))
<section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Rapor / Revisi {{ $report->revision_number }}</p><h2>{{ $display['student_name'] ?? $report->student->full_name }}</h2><p>{{ $report->cycle->start_month->locale('id')->translatedFormat('F Y') }} – {{ $report->cycle->end_month->locale('id')->translatedFormat('F Y') }} · {{ $report->branch->name }} · {{ $display['teacher_name'] ?? $report->teacher->user->full_name }}</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.index') }}"><x-dashboard.icon name="back" />Kembali ke Rapor Tiga Bulan</a></section>
<article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Status rapor final</h2><p>{{ $report->status === 'approved' ? 'Isi persetujuan tersimpan. PDF memakai isi, identitas, dan template saat persetujuan.' : 'Isi terkunci saat Diajukan. Perubahan setelah persetujuan perlu tinjauan kembali.' }}</p></div><x-dashboard.status :status="$report->status" :label="$statusLabels[$report->status] ?? 'Periksa status'" /></header><div class="dpe-dashboard-card__body">
@if($report->status === 'approved')<x-dashboard.approval :user="$report->approvedBy" :at="$report->approved_at" :timezone="$report->branch?->timezone_name" />@endif
@if(in_array($report->status, ['draft','revision'], true) || $report->status === 'submitted')<p><strong>{{ $readiness['completed'] }} / 4 skill lengkap</strong></p>
@if($readiness['errors'])<div class="dpe-final-notice">
@foreach($readiness['errors'] as $error)<p>{{ $error }}</p>
@endforeach</div>
@endif 
@endif
<div class="dpe-final-notice"><p><strong>Tanda tangan pada {{ $report->status === 'approved' ? 'salinan persetujuan' : 'preview saat ini' }}:</strong> Teacher {{ ($display['signatures']['teacher'] ?? null) ? 'tersedia' : 'belum tersedia' }} · Manager {{ ($display['signatures']['manager'] ?? null) ? 'tersedia' : 'belum tersedia' }}.</p>
<p>{{ $report->status === 'approved' ? 'Mengunggah tanda tangan baru tidak mengubah persetujuan ini. Untuk memperbaruinya, mulai revisi dan ajukan kembali.' : 'Unggah sebelum persetujuan agar masuk PDF. Teacher mengunggah melalui Profil Saya; Super Admin mengunggah tanda tangan Manager melalui Template PDF.' }}</p></div>
@if(!$action)<div class="dpe-final-actions">
<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.whatsapp.index', $report) }}">{{ $teacher || auth()->user()->role_code === 'superadmin' ? 'Kirim Rapor via WhatsApp' : 'Riwayat WhatsApp' }}</a>
@if(in_array($report->status, ['draft','revision'], true))<a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($routeBase.'.edit', $report) }}">{{ $teacher ? 'Isi / perbaiki rapor' : 'Ganti Teacher pemilik' }}</a>
@if($teacher)<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.confirm', [$report, 'submit']) }}">{{ $report->status === 'revision' ? 'Ajukan ulang rapor' : 'Ajukan rapor final' }}</a>
@endif 
@endif
@if(!$teacher && $report->status === 'submitted')<a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($routeBase.'.confirm', [$report, 'approve']) }}">Setujui rapor final</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.confirm', [$report, 'revision']) }}">Minta revisi</a>
@endif
@if($previewReady || $report->status === 'approved')<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.preview', $report) }}" target="_blank" rel="noopener">{{ $report->status === 'approved' ? 'Preview persetujuan' : 'Preview PDF draf' }}</a>
@endif
@if($gate['internal_pdf_ready'])<a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($routeBase.'.confirm', [$report, $currentFile ? 'regenerate' : 'generate']) }}">{{ $currentFile ? 'Buat ulang PDF' : 'Buat PDF final' }}</a>
@endif
@if($teacher && $report->status === 'approved')<a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.confirm', [$report, 'reopen']) }}">Mulai revisi baru</a>
@endif</div>
@endif
@if($action)
@php($actionLabels = ['submit' => 'Ya, ajukan rapor final', 'approve' => 'Ya, setujui rapor final', 'revision' => 'Ya, minta revisi', 'reopen' => 'Ya, mulai revisi baru', 'generate' => 'Ya, buat PDF final', 'regenerate' => 'Ya, buat ulang PDF'])
<div class="dpe-final-confirm"><h3>Konfirmasi tindakan</h3><p>{{ ['submit' => 'Rapor akan diajukan kepada Admin dan isinya dikunci selama tinjauan.', 'approve' => 'Pastikan empat skill dan Note sudah benar. Persetujuan ini terpisah dari persetujuan laporan bulanan.', 'revision' => 'Teacher dapat memperbaiki rapor setelah dikembalikan untuk revisi.', 'reopen' => 'Persetujuan saat ini dibuka menjadi draf revisi baru. PDF lama tetap tersimpan dan perubahan harus diajukan kembali.', 'generate' => 'PDF dibuat dari salinan persetujuan. Pembayaran tetap harus tervalidasi sebelum akses orang tua atau pengiriman dapat dibuka.', 'regenerate' => 'PDF baru dibuat dari salinan persetujuan yang sama. File lama tetap tersimpan sebagai riwayat.'][$action] }}</p>
<form method="POST" action="{{ route($routeBase.'.'.$action, $report) }}" class="dpe-dashboard-form">
@csrf<input type="hidden" name="expected_snapshot" value="{{ old('expected_snapshot', $expectedSnapshot) }}">
@if(in_array($action, ['revision','reopen','regenerate'], true))<div class="dpe-dashboard-field"><label for="final-comment">Alasan</label><textarea id="final-comment" name="comment" rows="3" maxlength="255" required>{{ old('comment') }}</textarea></div>
@endif
<div class="dpe-final-actions dpe-final-confirm-actions"><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" 
@disabled($stale || (in_array($action, ['submit','approve'], true) && !$readiness['ready']) || (in_array($action, ['generate','regenerate'], true) && !$gate['internal_pdf_ready']))>{{ $actionLabels[$action] }}</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.show', $report) }}">Batal</a></div></form>
@if($stale)<p class="dpe-final-notice">Data berubah. <a href="{{ route($routeBase.'.show', $report) }}">Muat rapor terbaru</a> sebelum mengonfirmasi kembali.</p>
@endif</div>
@endif
</div></article>
<article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Empat skill</h2><p>A: 90–100 (Very Good) · B: 80–89 (Good) · C: 70–79 (Fair)</p></div></header><div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-final-skills-table"><thead><tr><th>Skill</th><th>Deskripsi</th><th>Skor</th></tr></thead><tbody>
@foreach($skillLabels as $code => $label)<tr><td><strong>{{ $label }}</strong></td><td><div class="dpe-final-text">{{ $display['skills'][$code]['description'] ?? 'Belum diisi' }}</div></td><td><strong>{{ $display['skills'][$code]['score'] ?? '—' }}</strong></td></tr>
@endforeach</tbody></table></div><div class="dpe-dashboard-card__body"><h3>Note untuk orang tua</h3><div class="dpe-final-text">{{ $display['parent_note'] ?? 'Belum diisi' }}</div></div></article>
<article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>Kelengkapan tiga bulan</h2><p>PDF final internal memerlukan rapor final dan ketiga laporan bulanan disetujui. Akses orang tua dan pengiriman juga memerlukan pembayaran ketiga bulan tervalidasi.</p></div></header><div class="dpe-dashboard-card__body"><div class="dpe-final-source-grid">
@foreach($gate['months'] as $source)<div class="dpe-final-source"><strong>{{ $source['label'] }}</strong><p>Laporan: <x-dashboard.status :status="$source['monthly_ok'] ? 'approved' : 'pending'" :label="$source['monthly_ok'] ? 'Disetujui' : 'Belum disetujui'" /></p><p>Pembayaran: <x-dashboard.status :status="$source['paid'] ? 'approved' : 'pending'" :label="$source['paid'] ? 'Tervalidasi' : 'Belum tervalidasi'" /></p><a href="{{ route($monthlyBase.'.index', ['month' => substr($source['month'], 0, 7), 'status' => 'all', 'q' => $report->student->full_name]) }}">Periksa laporan bulanan</a></div>
@endforeach</div><div class="dpe-final-notice"><strong>{{ $gate['ready'] ? 'Syarat akses orang tua sudah terpenuhi.' : 'Syarat akses orang tua belum terpenuhi.' }}</strong><p>Rapor dapat dibuka di portal siswa setelah seluruh syarat terpenuhi dan PDF tersedia. Berbagi WhatsApp tersedia melalui pilihan tautan rapor atau PDF, dengan pengiriman akhir oleh petugas di WhatsApp.</p></div></div></article>
<article class="dpe-dashboard-card dpe-progress-spaced"><header class="dpe-dashboard-card__head"><div><h2>PDF tersimpan</h2><p>File hanya dapat dibuka oleh pengguna yang berhak atas rapor ini. Versi lama tetap tersimpan.</p></div></header>
@if($files->isEmpty())<x-dashboard.empty title="Belum ada PDF tersimpan" message="Lengkapi persetujuan rapor final dan tiga laporan bulanan, lalu pilih Buat PDF final." icon="report" />
@else<div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-final-pdf-table"><thead><tr><th>Versi PDF</th><th>Persetujuan</th><th>Dibuat</th><th>Aksi</th></tr></thead><tbody>
@foreach($files as $file)<tr><td>v{{ $file->version_number }}</td><td><div class="dpe-final-pdf-meta"><span>Revisi {{ $file->approval_revision }}</span><small>{{ $report->status === 'approved' && $file->approval_revision === $report->revision_number ? 'Persetujuan saat ini' : 'Arsip persetujuan sebelumnya' }}</small></div></td><td><div class="dpe-final-pdf-meta"><time datetime="{{ $file->generated_at->toIso8601String() }}">{{ $file->generated_at->format('d M Y H:i') }}</time><small>{{ $file->generatedBy?->full_name ?: '—' }}</small></div></td><td><div class="dpe-final-actions"><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($routeBase.'.download', [$report, $file]) }}" target="_blank" rel="noopener">Buka</a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routeBase.'.download', [$report, $file, 'download' => 1]) }}">Unduh</a></div></td></tr>
@endforeach</tbody></table></div>
@endif</article>
<article id="report-history" class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><h2>Riwayat rapor final</h2></header><div class="dpe-dashboard-card__body">
@forelse($history as $event)<div class="dpe-final-history"><strong>{{ ['prepared' => 'Draf disiapkan', 'draft_saved' => 'Draf disimpan', 'submit' => 'Rapor diajukan', 'approve' => 'Rapor disetujui', 'revision' => 'Revisi diminta', 'reopen' => 'Revisi baru dibuka', 'pdf_generated' => 'PDF dibuat', 'pdf_regenerated' => 'PDF dibuat ulang'][$event->event_type] ?? $event->event_type }}</strong><small>{{ $event->occurred_at->format('d M Y H:i') }} · {{ $event->actor?->full_name ?: '—' }}</small>
@if($event->comment)<p class="dpe-final-text">{{ $event->comment }}</p>
@endif</div>
@empty<p>Belum ada riwayat.</p>
@endforelse</div><x-dashboard.pagination :paginator="$history" /></article>
@endsection
