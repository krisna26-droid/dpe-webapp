@extends('layouts.dashboard')
@section('title', 'Detail Seri Jadwal')
@section('heading', 'Jadwal & Sesi')
@section('description', 'Pertemuan dalam satu seri beserta riwayat perubahan jadwal.')
@push('head') @include('lesson-series._head') @endpush
@section('content')
    @php
        $lessonList = app(\App\Services\LessonSessionListService::class);
        $listParameters = $lessonList->parameters(request());
    @endphp
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Seri jadwal mingguan</p>
            <h2>{{ $series->classGroup->name }}{{ $series->learningSession ? ' · '.$series->learningSession->name : '' }}</h2>
            <p>{{ $series->branch->name }} · Dibuat oleh {{ $series->createdBy?->full_name ?: 'Petugas' }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.lesson-sessions.index', $lessonList->backParameters(request())) }}">Kembali ke Jadwal & Sesi</a>
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($operationRoutePrefix.'.lesson-series.create', $listParameters) }}">Buat seri lain</a>
            <form method="POST" action="{{ route($operationRoutePrefix.'.lesson-series.destroy', [$series] + $listParameters) }}"
                data-confirm-form data-confirm-title="Hapus seluruh jadwal seri ini?"
                data-confirm-message="Seluruh {{ $deletion['count'] }} pertemuan dalam seri ini akan dihapus dari jadwal guru dan siswa. Periksa kembali seri yang dipilih sebelum melanjutkan."
                data-confirm-action="Hapus seluruh seri">
                @csrf
                @method('DELETE')
                <input type="hidden" name="deletion_version" value="{{ $deletion['version'] }}">
                <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit">
                    <x-dashboard.icon name="trash" /><span>Hapus seluruh jadwal seri</span>
                </button>
            </form>
        </div>
    </section>
    <div class="dpe-dashboard-form-note">
        <strong>Pilih pertemuan yang ingin dihapus</strong>
        <p>Gunakan tombol Hapus pertemuan ini pada tanggal yang dipilih. Tombol Hapus seluruh jadwal seri berlaku untuk seluruh {{ $deletion['count'] }} pertemuan di semua halaman.</p>
        <p>Pertemuan yang sudah memiliki absensi, catatan pembelajaran, video, atau jadwal pengganti dilindungi dari penghapusan.</p>
    </div>
    <article id="series-meetings" class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Pertemuan dalam seri</h2><p>{{ $sessions->total() }} pertemuan. Setiap tanggal memiliki absensi dan catatan tersendiri.</p></div></header><div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-series-table"><thead><tr><th>Tanggal / jam</th><th>Teacher</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
        @forelse($sessions as $session)
            <tr>
                <td>{{ $session->planned_start_at->locale('id')->translatedFormat('l, d F Y') }}<small>{{ $session->planned_start_at->format('H:i') }}–{{ $session->planned_end_at->format('H:i') }}</small></td>
                <td>{{ $session->teacher?->user?->full_name }}</td>
                <td>{{ ['scheduled'=>'Terjadwal','ongoing'=>'Berlangsung','completed'=>'Selesai','cancelled'=>'Dibatalkan'][$session->status] ?? $session->status }}</td>
                <td>
                    <div class="dpe-series-actions">
                        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.lesson-sessions.show', [$session] + $listParameters) }}">Detail</a>
                        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.lesson-sessions.edit', [$session] + $listParameters) }}">Pertemuan ini saja</a>
                        @if($session->planned_start_at->isFuture() && $session->status === 'scheduled')
                            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.lesson-series.edit', [$series, $session] + $listParameters) }}">Ini dan berikutnya</a>
                        @endif
                        <form method="POST" action="{{ route($operationRoutePrefix.'.lesson-series.destroy-meeting', [$series, $session, 'page' => $sessions->currentPage()] + $listParameters) }}"
                            data-confirm-form data-confirm-title="Hapus pertemuan ini?"
                            data-confirm-message="{{ $series->classGroup->name }}{{ $series->learningSession ? ' · '.$series->learningSession->name : '' }} · {{ $series->branch->name }} · {{ $session->teacher?->user?->full_name ?: 'Guru belum tersedia' }} · {{ $session->planned_start_at->locale('id')->translatedFormat('l, d F Y') }}, pukul {{ $session->planned_start_at->format('H:i') }}–{{ $session->planned_end_at->format('H:i') }}. Pertemuan pada tanggal ini akan dihapus dari jadwal guru dan siswa."
                            data-confirm-action="Hapus pertemuan ini">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="deletion_version" value="{{ $deletion['meeting_versions'][$session->id] }}">
                            <button class="dpe-dashboard-button dpe-dashboard-button--danger" type="submit">
                                <x-dashboard.icon name="trash" /><span>Hapus pertemuan ini</span>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="4">Tidak ada pertemuan tersisa dalam seri ini.</td></tr>
        @endforelse
    </tbody></table></div><x-dashboard.pagination :paginator="$sessions" /></article>
    <article id="series-history" class="dpe-dashboard-card dpe-series-history"><header class="dpe-dashboard-card__head"><h2>Riwayat perubahan seri</h2></header><div class="dpe-dashboard-card__body">
        @forelse($changes as $change)<p>{{ $change->created_at->format('d/m/Y H:i') }} · {{ $change->actor?->full_name ?: 'Petugas' }} · {{ count($change->after_snapshot) }} pertemuan diperbarui</p>@empty<p>Belum ada perubahan massal setelah seri dibuat.</p>@endforelse
    </div><x-dashboard.pagination :paginator="$changes" /></article>
@endsection
