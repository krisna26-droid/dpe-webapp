@php
    $sessionOptions=app(\App\Services\ClassLearningSessionService::class)->options(auth()->user());
    $current=$current ?? null;
    $value=old('class_learning_session_id',$selectedId ?? $current?->class_learning_session_id);
    $nameValue=old('learning_session_name',$newName ?? '');
@endphp
<div class="dpe-dashboard-field" data-session-picker>
    <label for="class_learning_session_id">Sesi belajar</label>
    <select id="class_learning_session_id" name="class_learning_session_id" data-session-existing><option value="">Buat sesi baru</option>@foreach($sessionOptions as $slot)<option value="{{ $slot->id }}" data-group="{{ $slot->class_group_id }}" @selected($value===$slot->id)>{{ $slot->name }} · {{ $slot->classGroup?->name }}</option>@endforeach</select>
    <label for="learning_session_name" data-session-new-label>Nama sesi baru</label><input id="learning_session_name" name="learning_session_name" value="{{ $nameValue }}" maxlength="100" placeholder="Contoh: Sesi 1" data-session-new>
    <p class="dpe-dashboard-field__hint">Pilih sesi yang sama ketika melanjutkan jadwal mingguan. Siswa mengikuti sesi yang dipilih pada keanggotaannya.</p>
    @error('class_learning_session_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror @error('learning_session_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
</div>
@once @push('scripts')<script src="{{ asset('assets/dpe/class-sessions.js') }}?v={{ filemtime(public_path('assets/dpe/class-sessions.js')) }}" defer></script>@endpush @endonce
