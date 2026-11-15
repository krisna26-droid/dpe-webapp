@php
    $userRouteBase = auth()->user()->role_code === 'superadmin' ? 'superadmin' : 'admin';
@endphp
<article class="dpe-dashboard-card">
    <div class="dpe-dashboard-card__body">
        <form method="GET" action="{{ route($userRouteBase.'.users.index') }}" class="dpe-dashboard-form" role="search" aria-label="Filter pengguna">
            <div class="dpe-dashboard-form-grid {{ $userRouteBase === 'superadmin' ? 'dpe-dashboard-form-grid--three' : '' }}">
                <div class="dpe-dashboard-field">
                    <label for="user-search">Cari pengguna</label>
                    <input id="user-search" name="q" type="search" maxlength="100" value="{{ $filters['q'] }}" placeholder="Nama, username, atau email">
                    @error('q')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                @if ($userRouteBase === 'superadmin')
                    <div class="dpe-dashboard-field">
                        <label for="user-role">Role</label>
                        <select id="user-role" name="role_code">
                            <option value="">Semua role</option>
                            @foreach (['superadmin' => 'Super Admin', 'admin' => 'Admin Cabang', 'teacher' => 'Guru', 'student' => 'Siswa / Parent'] as $code => $label)
                                <option value="{{ $code }}" @selected($filters['role_code'] === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('role_code')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div class="dpe-dashboard-field">
                    <label for="user-status">Status akun</label>
                    <select id="user-status" name="status">
                        <option value="">Semua status</option>
                        <option value="active" @selected($filters['status'] === 'active')>Aktif</option>
                        <option value="inactive" @selected($filters['status'] === 'inactive')>Nonaktif</option>
                    </select>
                    @error('status')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="dpe-dashboard-form-actions">
                <button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Tampilkan</button>
                <a href="{{ route($userRouteBase.'.users.index') }}" class="dpe-dashboard-button dpe-dashboard-button--outline">Reset</a>
            </div>
        </form>
    </div>
</article>
