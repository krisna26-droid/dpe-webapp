@php
    $sessionService = app(\App\Services\ClassLearningSessionService::class);
    $choices = $sessionService->choices($sessionService->options(auth()->user()));
    $effective = old('session_effective_on', today()->toDateString());
    $saved = $membership?->learningSessionEnrollments
        ->filter(fn($link) => $link->starts_on->toDateString() <= $effective && (!$link->ends_on || $link->ends_on->toDateString() >= $effective))
        ->map(fn($link) => $sessionService->selectionKey($link->class_learning_session_id, $link->weekday))->all() ?? [];
    $selected = (array) old('learning_session_ids', $saved);
    $choose = (bool) old('select_learning_sessions', !$membership || (bool) $membership->session_selection_from);
    $ended = $membership?->ends_on && $membership->ends_on->lt(today());
@endphp
<div class="dpe-dashboard-field dpe-dashboard-field--full" data-membership-sessions>
    @if(!$ended)
        @if($membership)
            <input type="hidden" name="select_learning_sessions" value="0">
            <label class="dpe-session-choice">
                <input type="checkbox" name="select_learning_sessions" value="1" data-membership-enable @checked($choose)>
                <span>Atur sesi yang diikuti siswa</span>
            </label>
            @if(!$membership->session_selection_from)
                <p class="dpe-dashboard-field__hint">Data lama mengikuti semua sesi kelas. Aktifkan pilihan ini untuk membatasi sesi mulai tanggal berlaku.</p>
            @endif
        @else
            <input type="hidden" name="select_learning_sessions" value="1">
        @endif
        <fieldset class="dpe-session-choices" data-membership-options>
            <legend>Sesi belajar</legend>
            <div class="dpe-session-days">
                @foreach($choices->groupBy(fn($choice) => $choice['weekday'] ?? 0) as $dayChoices)
                    <div class="dpe-session-day" data-membership-day>
                        <h3 class="dpe-session-day__title">{{ $dayChoices->first()['day_name'] }}</h3>
                        @foreach($dayChoices as $choice)
                            <label class="dpe-session-choice" data-membership-slot data-group="{{ $choice['session']->class_group_id }}">
                                <input type="checkbox" name="learning_session_ids[]" value="{{ $choice['key'] }}"
                                    @checked(in_array($choice['key'], $selected, true) || in_array($choice['id'], $selected, true))>
                                <span><strong>{{ $choice['session']->name }} · {{ $choice['session']->classGroup?->name }}</strong><small>{{ $choice['details'] }}</small></span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <p class="dpe-dashboard-field__hint" data-membership-empty role="status">Pilih kelompok kelas. Jika sesi belum tersedia, buat jadwal kelompok terlebih dahulu.</p>
            <p class="dpe-dashboard-field__hint">Pilih hari dan sesi yang diikuti siswa. Boleh memilih beberapa sesi pada hari yang sama.</p>
            @if($membership)
                <label for="session_effective_on">Berlaku mulai</label>
                <input id="session_effective_on" name="session_effective_on" type="date" value="{{ $effective }}" min="{{ today()->toDateString() }}">
                <p class="dpe-dashboard-field__hint">Perubahan berlaku mulai tanggal ini. Riwayat sesi sebelumnya tetap tersimpan.</p>
            @endif
        </fieldset>
        @error('learning_session_ids')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        @error('session_effective_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
    @else
        <p class="dpe-dashboard-field__hint">Periode keanggotaan sudah selesai. Pilihan sesi tersimpan sebagai riwayat.</p>
    @endif
</div>
@once
    @push('scripts')
        <script src="{{ asset('assets/dpe/class-sessions.js') }}?v={{ filemtime(public_path('assets/dpe/class-sessions.js')) }}" defer></script>
    @endpush
@endonce
