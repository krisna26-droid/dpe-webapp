@php
    $branch = $branch ?? null;
    $isEdit = isset($branch) && $branch;
    $formAction = $isEdit
        ? route('superadmin.branches.update', $branch)
        : route('superadmin.branches.store');
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $activeValue = old('is_active', $isEdit ? (int) $branch->is_active : 1);
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
            <label for="code">Kode cabang</label>
            <input id="code" name="code" type="text" value="{{ old('code', $branch?->code) }}" placeholder="Contoh: DPS" maxlength="255" required>
            <p class="dpe-dashboard-field__hint">Kode unik untuk identifikasi internal cabang.</p>
            @error('code')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="name">Nama cabang</label>
            <input id="name" name="name" type="text" value="{{ old('name', $branch?->name) }}" placeholder="Contoh: DPE Denpasar" maxlength="255" required>
            @error('name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="timezone_name">Zona waktu</label>
            <input id="timezone_name" name="timezone_name" type="text" value="{{ old('timezone_name', $branch?->timezone_name ?: 'Asia/Makassar') }}" placeholder="Asia/Makassar" maxlength="255" required>
            <p class="dpe-dashboard-field__hint">Gunakan nama zona waktu PHP, misalnya Asia/Makassar.</p>
            @error('timezone_name')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field">
            <label for="payment_recap_day">Tanggal rekap pembayaran</label>
            <input id="payment_recap_day" name="payment_recap_day" type="number" value="{{ old('payment_recap_day', $branch?->payment_recap_day ?: 20) }}" min="1" max="31" required>
            <p class="dpe-dashboard-field__hint">Tanggal rekap dapat berbeda untuk setiap cabang.</p>
            @error('payment_recap_day')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>

        <div class="dpe-dashboard-field dpe-dashboard-field--full">
            <label for="address">Alamat cabang <span>(opsional)</span></label>
            <textarea id="address" name="address" placeholder="Alamat lengkap cabang">{{ old('address', $branch?->address) }}</textarea>
            @error('address')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
        </div>


    </div>

    <div class="dpe-dashboard-form-actions">
        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.branches.index') }}">Batal</a>
        <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">
            <x-dashboard.icon name="check" />
            <span>{{ $isEdit ? 'Simpan perubahan' : 'Simpan cabang' }}</span>
        </button>
    </div>
</form>
