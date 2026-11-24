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
                @if ($userRouteBase === 'superadmin')
                    <div class="dpe-dashboard-field">
                        <label for="user-branch">Cabang</label>
                        <select id="user-branch" name="branch_id">
                            <option value="">Semua cabang</option>
                            @foreach ($accountBranches as $id => $name)
                                <option value="{{ $id }}" @selected($filters['branch_id'] === $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div class="dpe-dashboard-field">
                    <label for="user-program">Program</label>
                    <select id="user-program" name="program_id">
                        <option value="">Semua program</option>
                        @foreach ($accountPrograms as $id => $name)
                            <option value="{{ $id }}" @selected($filters['program_id'] === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('program_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
                <div class="dpe-dashboard-field">
                    <label for="user-teacher">Guru utama</label>
                    <select id="user-teacher" name="teacher_id">
                        <option value="">Semua guru</option>
                        @foreach ($accountTeachers as $id => $name)
                            <option value="{{ $id }}" @selected($filters['teacher_id'] === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('teacher_id')<p class="dpe-dashboard-field__error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="dpe-dashboard-form-actions">
                <button type="submit" class="dpe-dashboard-button dpe-dashboard-button--primary">Tampilkan</button>
                <a href="{{ route($userRouteBase.'.users.index') }}" class="dpe-dashboard-button dpe-dashboard-button--outline">Reset</a>
            </div>
            <p class="dpe-dashboard-field__hint" style="margin-top: 12px;">Ekspor PDF/Excel memuat hanya akun siswa dari seluruh halaman sesuai filter yang telah diterapkan, tanpa kata sandi.
                @if ($userRouteBase === 'superadmin') Filter cabang, program, dan guru utama khusus akun siswa (pilih Semua role atau Siswa / Parent). @endif
            </p>
            @error('export')<p class="dpe-dashboard-field__error" role="alert">{{ $message }}</p>@enderror
        </form>
    </div>
</article>
