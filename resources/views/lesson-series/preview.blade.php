@extends('layouts.dashboard')
@section('title', 'Pratinjau Jadwal')
@section('heading', 'Jadwal & Sesi')
@section('description', 'Periksa tanggal, pengecualian dan bentrok sebelum menyimpan.')
@push('head') @include('lesson-series._head') @endpush
@section('content')
    @php
        $lessonList = app(\App\Services\LessonSessionListService::class);
        $listParameters = $lessonList->parameters(request());
        $isCreate = $operation === 'create';
        $selected = collect($rows)->filter(fn($row) => !$row['excluded'] && !$row['preserved']);
        $problems = $selected->filter(fn($row) => count($row['issues']) > 0)->count();
        $preserved = collect($rows)->filter(fn($row) => $row['preserved'])->count();
        $refreshUrl = $isCreate ? route($operationRoutePrefix.'.lesson-series.preview', $listParameters) : route($operationRoutePrefix.'.lesson-series.preview-future', [$series, $anchor] + $listParameters);
        $saveUrl = $isCreate ? route($operationRoutePrefix.'.lesson-series.store', $listParameters) : route($operationRoutePrefix.'.lesson-series.update-future', [$series, $anchor] + $listParameters);
        $backUrl = $isCreate ? route($operationRoutePrefix.'.lesson-series.create', ['draft' => $formDraftId] + $listParameters) : route($operationRoutePrefix.'.lesson-series.edit', [$series, $anchor, 'draft' => $formDraftId] + $listParameters);
    @endphp
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">{{ $isCreate ? 'Seri baru' : 'Perubahan pertemuan ini dan berikutnya' }}</p><h2>Periksa jadwal sebelum disimpan</h2><p>{{ $refs['group']->name }} · {{ $refs['branch']->name }} · {{ $refs['teacher']?->user?->full_name }}</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $backUrl }}">Kembali ke form</a></section>
    @if($errors->any())<div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert"><div><strong>Belum dapat disimpan.</strong><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div></div>@endif
    <div class="dpe-dashboard-form-note"><p><strong>{{ $selected->count() }} pertemuan dipilih</strong> · {{ $problems }} perlu diperbaiki @if(!$isCreate) · {{ $preserved }} dipertahankan @endif</p><p>{{ $isCreate ? 'Centang Kecualikan pada tanggal yang tidak digunakan, lalu perbarui pratinjau. Jadwal belum dibuat pada tahap ini.' : 'Pertemuan yang sudah berjalan, dibatalkan, memiliki absensi/catatan atau jadwal pengganti dipertahankan. Perubahan hanya diterapkan pada baris yang siap.' }}</p><p>Bentrok diperiksa lagi saat penyimpanan. Jika ada masalah, seluruh penyimpanan dibatalkan agar tidak terbentuk sebagian jadwal.</p></div>
    <form method="POST" action="{{ $saveUrl }}" class="dpe-series-preview">
        @csrf
        <input type="hidden" name="preview_token" value="{{ $previewToken }}">
        <input type="hidden" name="draft_id" value="{{ $formDraftId }}">
        <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Daftar tanggal</h2><p>Jam mengikuti zona {{ $refs['branch']->timezone_name }}. Pratinjau berlaku satu jam.</p></div></header><div class="dpe-dashboard-table-wrap"><table class="dpe-dashboard-table dpe-series-table"><thead><tr><th>Tanggal</th><th>Jam</th><th>Hasil pemeriksaan</th>@if($isCreate)<th>Pilihan</th>@endif</tr></thead><tbody>
            @foreach($rows as $index => $row)
                <tr>
                    <td>{{ \Carbon\CarbonImmutable::parse($row['data']['planned_start_at'])->locale('id')->translatedFormat('l, d F Y') }}@if(!$isCreate)<small>Semula {{ $row['session']->planned_start_at->format('d/m/Y H:i') }}–{{ $row['session']->planned_end_at->format('H:i') }}</small>@endif</td>
                    <td>{{ \Carbon\CarbonImmutable::parse($row['data']['planned_start_at'])->format('H:i') }}–{{ \Carbon\CarbonImmutable::parse($row['data']['planned_end_at'])->format('H:i') }}</td>
                    <td>@if($row['excluded'])<span class="dpe-dashboard-badge">Dikecualikan</span>@elseif($row['preserved'])<span class="dpe-dashboard-badge">Dipertahankan</span><small>{{ $row['preserved'] }}</small>@elseif($row['issues'])<span class="dpe-dashboard-badge dpe-dashboard-badge--warning">Perlu diperbaiki</span>@foreach($row['issues'] as $issue)<small>{{ $issue }}</small>@endforeach @else<span class="dpe-dashboard-badge dpe-dashboard-badge--success">{{ $row['data']['status'] === 'cancelled' ? 'Siap dibatalkan' : 'Siap disimpan' }}</span>@endif</td>
                    @if($isCreate)<td><label class="dpe-series-exclusion"><input type="checkbox" name="excluded_dates[]" value="{{ $row['date'] }}" @checked($row['excluded'])> Kecualikan</label></td>@endif
                </tr>
            @endforeach
        </tbody></table></div></article>
        <div class="dpe-dashboard-form-actions"><button class="dpe-dashboard-button dpe-dashboard-button--outline" type="submit" formnovalidate formaction="{{ route($operationRoutePrefix.'.lesson-series.cancel', $listParameters) }}">Batal</button><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $backUrl }}">Kembali</a><button class="dpe-dashboard-button dpe-dashboard-button--outline" type="submit" formaction="{{ $refreshUrl }}">Perbarui pratinjau</button><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">{{ $isCreate ? 'Simpan jadwal terpilih' : 'Simpan perubahan seri' }}</button></div>
    </form>
@endsection
