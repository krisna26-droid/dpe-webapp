@extends('layouts.dashboard')

@section('title', 'Manajemen Pengguna')
@section('heading', 'Pengguna')
@section('description', 'Lihat akun, role, dan status akses pengguna portal DPE.')

@section('content')
    @php
        $roleLabels = [
            'superadmin' => 'Super Admin',
            'admin' => 'Admin Cabang',
            'teacher' => 'Guru',
            'student' => 'Siswa / Parent',
        ];
    @endphp

    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Akses</p>
            <h2>Manajemen pengguna</h2>
            <p>Daftar akun yang dapat masuk ke portal beserta role dan statusnya.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.users.create') }}">
                <x-dashboard.icon name="plus" />
                <span>Tambah pengguna</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    @if (session('created_student_user_id'))
        <div class="dpe-dashboard-form-note" role="status">
            <strong>Lanjutkan dengan membuat profil siswa.</strong>
            <p>Akun portal sudah dibuat. Lengkapi profil anak agar siswa dapat dipilih pada form tagihan dan kegiatan belajar.</p>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.students.create', ['portal_user_id' => session('created_student_user_id')]) }}">
                <x-dashboard.icon name="plus" />
                <span>Tambah profil siswa</span>
            </a>
        </div>
    @endif

    @if (session('created_teacher_user_id'))
        <div class="dpe-dashboard-form-note" role="status">
            <strong>Lanjutkan dengan membuat profil guru.</strong>
            <p>Akun login guru sudah dibuat. Tambahkan nomor WhatsApp dan profil guru agar akun dapat dipilih pada penugasan cabang.</p>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('admin.teachers.create', ['user_id' => session('created_teacher_user_id')]) }}">
                <x-dashboard.icon name="plus" />
                <span>Tambah profil guru</span>
            </a>
        </div>
    @endif

    @if (session('created_admin_user_id'))
        <div class="dpe-dashboard-form-note" role="status">
            <strong>Lanjutkan dengan menugaskan admin ke cabang.</strong>
            <p>Akun Admin Cabang sudah dibuat. Pilih cabang dan periode tanggung jawab agar akun dapat digunakan untuk operasional cabang.</p>
            <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.branch-admin-assignments.create', ['admin_user_id' => session('created_admin_user_id')]) }}">
                <x-dashboard.icon name="plus" />
                <span>Tambah penugasan admin</span>
            </a>
        </div>
    @endif

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar pengguna</h2>
                <p>{{ $users->total() }} akun tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="users" /> Akses portal</span>
        </header>

        @if ($users->count() === 0)
            <x-dashboard.empty
                title="Belum ada pengguna"
                message="Belum ada akun lain yang tersimpan di sistem."
                icon="users"
                :action-url="route('superadmin.users.create')"
                action-label="Tambah pengguna"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td><strong>{{ $user->full_name }}</strong></td>
                                <td>{{ $user->username }}</td>
                                <td>{{ $roleLabels[$user->role_code] ?? $user->role_code }}</td>
                                <td>{{ $user->email ?: '—' }}</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $user->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">
                                        {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td>{{ $user->created_at?->format('d M Y') ?? '—' }}</td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.users.show', $user) }}">Detail</a> <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.users.edit', $user) }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$users" />
        @endif
    </article>
@endsection
