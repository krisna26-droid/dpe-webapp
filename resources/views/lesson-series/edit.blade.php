@extends('layouts.dashboard')
@section('title', 'Ubah Pertemuan Berikutnya')
@section('heading', 'Jadwal & Sesi')
@section('description', 'Ubah pertemuan terpilih dan pertemuan berikutnya yang masih dapat dijadwalkan.')
@push('head') @include('lesson-series._head') @endpush
@section('content')
    @php($formValue = fn($field, $default = null) => array_key_exists($field, $formValues) ? $formValues[$field] : $default)
    <x-dashboard.form-panel title="Pertemuan ini dan berikutnya" description="Satu seri tetap memakai kelompok, cabang, program dan lokasi yang sama. Perubahan akan ditampilkan dahulu pada pratinjau." :close-url="route($operationRoutePrefix.'.lesson-series.show', $series)">
        @if($errors->any())<div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert"><ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
        <p>{{ $series->classGroup->name }} · {{ $series->branch->name }}</p>
        <p class="dpe-dashboard-field__hint">Mulai dari {{ $anchor->planned_start_at->locale('id')->translatedFormat('l, d F Y H:i') }}. Pertemuan sebelumnya tetap tersimpan. Jika tanggal acuan digeser, tanggal berikutnya bergeser dengan jumlah hari yang sama; pengecualian yang sudah dibuat tetap diikuti.</p>
        <form method="POST" action="{{ route($operationRoutePrefix.'.lesson-series.preview-future', [$series, $anchor]) }}">
            @csrf
            @if($formDraftId)<input type="hidden" name="draft_id" value="{{ $formDraftId }}">@endif
            <div class="dpe-dashboard-form-grid">
                <div class="dpe-dashboard-field"><label for="series-teacher">Teacher untuk pertemuan berikutnya</label><select id="series-teacher" name="teacher_id" required>@foreach($teachers as $teacher)<option value="{{ $teacher->id }}" @selected($formValue('teacher_id', $anchor->teacher_id) === $teacher->id)>{{ $teacher->user?->full_name ?: $teacher->user?->username }}{{ $teacher->is_active ? '' : ' (nonaktif)' }}</option>@endforeach</select></div>
                <div class="dpe-dashboard-field"><label for="series-status">Tindakan</label><select id="series-status" name="status" required><option value="scheduled" @selected($formValue('status', 'scheduled') === 'scheduled')>Perbarui jadwal</option><option value="cancelled" @selected($formValue('status') === 'cancelled')>Batalkan pertemuan yang masih dapat diubah</option></select><p class="dpe-dashboard-field__hint">Untuk pembatalan, hanya status yang berubah; tanggal dan catatan tidak dipindahkan.</p></div>
                <div class="dpe-dashboard-field"><label for="series-start">Mulai rencana pertemuan acuan</label><input id="series-start" name="planned_start_at" type="datetime-local" required value="{{ $formValue('planned_start_at', $anchor->planned_start_at->format('Y-m-d\TH:i')) }}"></div>
                <div class="dpe-dashboard-field"><label for="series-end">Selesai rencana pertemuan acuan</label><input id="series-end" name="planned_end_at" type="datetime-local" required value="{{ $formValue('planned_end_at', $anchor->planned_end_at->format('Y-m-d\TH:i')) }}"></div>
                <div class="dpe-dashboard-field"><label for="series-topic">Topik awal <span>(opsional)</span></label><input id="series-topic" name="topic" maxlength="255" value="{{ $formValue('topic', $anchor->topic) }}"></div>
                <div class="dpe-dashboard-field"><label for="series-material">Materi awal <span>(opsional)</span></label><input id="series-material" name="material" maxlength="255" value="{{ $formValue('material', $anchor->material) }}"></div>
                <div class="dpe-dashboard-field"><label for="series-activity">Aktivitas awal <span>(opsional)</span></label><input id="series-activity" name="activity" maxlength="255" value="{{ $formValue('activity', $anchor->activity) }}"></div>
            </div>
            <p class="dpe-dashboard-field__hint">Teacher, jam dan catatan awal diterapkan pada pertemuan yang siap diubah. Pertemuan dengan catatan belajar/absensi atau jadwal pengganti tidak ikut diubah.</p>
            <div class="dpe-dashboard-form-actions"><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix.'.lesson-sessions.edit', $anchor) }}">Pertemuan ini saja</a><button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Pratinjau perubahan</button></div>
        </form>
    </x-dashboard.form-panel>
@endsection
