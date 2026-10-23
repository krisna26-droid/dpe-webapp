@extends('layouts.dashboard')

@section('title', 'Penugasan Admin Cabang')
@section('heading', 'Admin Cabang')
@section('description', 'Pantau hubungan admin dengan cabang yang menjadi tanggung jawabnya.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Penugasan</p>
            <h2>Penugasan admin cabang</h2>
            <p>Daftar penempatan admin aktif dan riwayat penugasannya.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.branch-admin-assignments.create') }}">
                <x-dashboard.icon name="plus" />
                <span>Tambah penugasan</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar penugasan</h2>
                <p>{{ $assignments->total() }} penugasan tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="users" /> Admin cabang</span>
        </header>

        @if ($assignments->count() === 0)
            <x-dashboard.empty
                title="Belum ada penugasan admin"
                message="Penugasan admin cabang akan tampil setelah data assignment tersedia."
                icon="users"
                :action-url="route('superadmin.branch-admin-assignments.create')"
                action-label="Tambah penugasan"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Cabang</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignments as $assignment)
                            @php($active = $assignment->starts_on?->lte(now()->startOfDay()) && ($assignment->ends_on === null || $assignment->ends_on->gte(now()->startOfDay())))
                            <tr>
                                <td>
                                    <strong>{{ $assignment->admin?->full_name ?: ($assignment->admin?->username ?: '—') }}</strong>
                                </td>
                                <td>
                                    <strong>{{ $assignment->branch?->name ?: '—' }}</strong>
                                    @if ($assignment->branch?->code)<br><span>{{ $assignment->branch->code }}</span>@endif
                                </td>
                                <td>{{ $assignment->starts_on?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $assignment->ends_on?->format('d M Y') ?? 'Berjalan' }}</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
                                        {{ $active ? 'Aktif' : 'Selesai' }}
                                    </span>
                                </td>
                                <td>
                                    <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.branch-admin-assignments.show', $assignment) }}">Detail</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$assignments" />
        @endif
    </article>
@endsection
