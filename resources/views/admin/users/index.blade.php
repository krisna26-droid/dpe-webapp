@extends('layouts.dashboard')

@section('title', 'Pengguna Siswa')
@section('heading', 'Pengguna')
@section('description', 'Buat akun siswa yang langsung terdaftar pada cabang yang Anda kelola.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional Cabang / Akses</p>
            <h2>Pengguna siswa</h2>
            <p>Akun siswa dengan pendaftaran berjalan atau akan datang pada cabang Anda.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('admin.users.create') }}">
                <x-dashboard.icon name="plus" /><span>Tambah pengguna</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div><h2>Daftar pengguna</h2><p>{{ $users->total() }} akun siswa tercatat</p></div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="student" /> Siswa / Parent</span>
        </header>
        @if ($users->isEmpty())
            <x-dashboard.empty title="Belum ada pengguna siswa" message="Tambahkan akun siswa beserta program belajarnya. Pendaftaran cabang akan dibuat otomatis." icon="users" :action-url="route('admin.users.create')" action-label="Tambah pengguna" />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead><tr><th>Nama siswa</th><th>Username</th><th>Email</th><th>Cabang / program</th><th>Status akun</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($users as $account)
                            <tr>
                                <td><strong>{{ $account->full_name }}</strong></td>
                                <td>{{ $account->username }}</td>
                                <td>{{ $account->email ?: '—' }}</td>
                                <td>
                                    @foreach ($account->studentProfile->enrollments as $enrollment)
                                        <p>{{ $enrollment->branch->name }} / {{ $enrollment->program->name }}</p>
                                    @endforeach
                                </td>
                                <td><span class="dpe-dashboard-badge {{ $account->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('admin.users.show', $account) }}">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$users" />
        @endif
    </article>
@endsection
