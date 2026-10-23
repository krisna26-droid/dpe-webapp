@php
    $monthlyCharge = $monthlyCharge ?? null;
    $isEdit = $monthlyCharge !== null;
    $formAction = $isEdit
        ? route('superadmin.monthly-charges.update', $monthlyCharge)
        : route('superadmin.monthly-charges.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $selectedStudent = old('student_id', $monthlyCharge?->student_id);
    $selectedBranch = old('branch_id', $monthlyCharge?->branch_id);
    $chargeMonth = old(
        'charge_month',
        $monthlyCharge?->charge_month?->format('Y-m-d') ?: now()->startOfMonth()->format('Y-m-d')
    );
    $amount = old('amount_idr', $monthlyCharge?->amount_idr);
    $dueOn = old('due_on', $monthlyCharge?->due_on?->format('Y-m-d'));
@endphp

@if ($errors->any())
    <div class="dpe-dashboard-alert dpe-dashboard-alert--error" role="alert">
        <x-dashboard.icon name="warning" />
        <span>Periksa kembali field yang ditandai sebelum menyimpan tagihan.</span>
    </div>
@endif

<form method="POST" action="{{ $formAction }}">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="dpe-dashboard-form-grid">
        <div class="dpe-dashboard-field">
            <label for="student_id">Siswa</label>
            <select id="student_id" name="student_id" required>
                <option value="">Pilih siswa</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @selected((string) $selectedStudent === (string) $student->id)>
                        {{ $student->full_name }}{{ $student->status === 'active' ? '' : ' (nonaktif)' }}
                        @if ($student->portalUser?->username) · {{ $student->portalUser->username }}@endif
                    </option>
                @endforeach
            </select>
            @if ($students->isEmpty())
                <p class="dpe-dashboard-field__hint">Belum ada siswa aktif yang dapat dipilih.</p>
            @endif
            @error('student_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="branch_id">Cabang</label>
            <select id="branch_id" name="branch_id" required>
                <option value="">Pilih cabang</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) $selectedBranch === (string) $branch->id)>
                        {{ $branch->name }}{{ $branch->is_active ? '' : ' (nonaktif)' }} · {{ $branch->code }}
                    </option>
                @endforeach
            </select>
            @if ($branches->isEmpty())
                <p class="dpe-dashboard-field__hint">Belum ada cabang aktif yang dapat dipilih.</p>
            @endif
            @error('branch_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="charge_month">Bulan tagihan</label>
            <input id="charge_month" name="charge_month" type="date" value="{{ $chargeMonth }}" required>
            <p class="dpe-dashboard-field__hint">Satu siswa hanya boleh memiliki satu tagihan untuk bulan yang sama.</p>
            @error('charge_month')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="amount_idr">Nominal tagihan (Rp) <span>(opsional)</span></label>
            <input id="amount_idr" name="amount_idr" type="number" min="0" max="999999999999.99" step="0.01" inputmode="decimal" value="{{ $amount }}" placeholder="Kosongkan jika belum ditetapkan">
            <p class="dpe-dashboard-field__hint">Isi nominal yang sudah ditetapkan lembaga. Nominal kosong tetap memungkinkan unggah dan validasi bukti pembayaran.</p>
            @error('amount_idr')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="due_on">Jatuh tempo <span>(opsional)</span></label>
            <input id="due_on" name="due_on" type="date" value="{{ $dueOn }}">
            <p class="dpe-dashboard-field__hint">Kosongkan jika tanggal jatuh tempo belum ditetapkan.</p>
            @error('due_on')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="dpe-dashboard-form-note">
        <strong>Periksa periode dan siswa</strong>
        Pastikan siswa dan bulan tagihan benar sebelum menyimpan. Data ini menjadi acuan untuk penerimaan bukti pembayaran.
    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $isEdit ? route('superadmin.monthly-charges.show', $monthlyCharge) : route('superadmin.monthly-charges.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit" @disabled($students->isEmpty() || $branches->isEmpty())>
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan tagihan' }}</span>
        </button>
    </div>
</form>
