@php
    $assignment = $assignment ?? null;
    $isEdit = $assignment !== null;
    $formAction = $isEdit
        ? route('superadmin.branch-admin-assignments.update', $assignment)
        : route('superadmin.branch-admin-assignments.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedBranch = old('branch_id', $isEdit ? $assignment->branch_id : request('branch_id'));
    $selectedAdmin = old('admin_user_id', $isEdit ? $assignment->admin_user_id : request('admin_user_id'));
    $startsOn = old('starts_on', $assignment?->starts_on?->format('Y-m-d'));
    $endsOn = old('ends_on', $assignment?->ends_on?->format('Y-m-d'));
@endphp

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan.</span>
    </div>
@endif

<form method="POST" action="{{ $formAction }}">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="dpe-dashboard-form-grid">
        <div class="dpe-dashboard-field">
            <label for="admin_user_id">Admin cabang</label>
            <select id="admin_user_id" name="admin_user_id" required>
                <option value="">Pilih admin cabang</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected((string) $selectedAdmin === (string) $admin->id)>
                        {{ $admin->full_name ?: $admin->username }} ({{ $admin->username }})
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Hanya akun aktif dengan role Admin Cabang yang dapat dipilih.</p>
            @if ($admins->isEmpty())
                <p class="dpe-dashboard-field__error">Belum ada akun Admin Cabang aktif yang dapat ditugaskan.</p>
                <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.users.create', ['role_code' => 'admin']) }}">
                    <x-dashboard.icon name="plus" />
                    <span>Buat akun Admin Cabang</span>
                </a>
            @endif
            @error('admin_user_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="branch_id">Cabang</label>
            <select id="branch_id" name="branch_id" required>
                <option value="">Pilih cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>
                        {{ $branch->name }} ({{ $branch->code }})
                    </option>
                @endforeach
            </select>
            <p class="dpe-dashboard-field__hint">Cabang nonaktif tidak dapat menerima penugasan baru.</p>
            @if ($branches->isEmpty())
                <p class="dpe-dashboard-field__error">Belum ada cabang aktif yang dapat dipilih.</p>
                <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.branches.create') }}">
                    <x-dashboard.icon name="plus" />
                    <span>Buat cabang</span>
                </a>
            @endif
            @error('branch_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="starts_on">Tanggal mulai</label>
            <input id="starts_on" name="starts_on" type="date" value="{{ $startsOn }}" required>
            @error('starts_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="ends_on">Tanggal berakhir <span>(opsional)</span></label>
            <input id="ends_on" name="ends_on" type="date" value="{{ $endsOn }}">
            <p class="dpe-dashboard-field__hint">Kosongkan jika tanggung jawab admin masih berjalan.</p>
            @error('ends_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-note">
        <strong>Periksa akses cabang</strong>
        Satu cabang hanya memiliki satu admin pada periode yang sama, dan satu admin hanya mengelola satu cabang pada periode yang sama. Untuk pergantian admin, akhiri penugasan lama sehari sebelum tanggal mulai admin baru.
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.branch-admin-assignments.show', $assignment) : route('superadmin.branch-admin-assignments.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($admins->isEmpty() || $branches->isEmpty())>
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan penugasan' }}</span>
        </button>
    </div>
</form>
