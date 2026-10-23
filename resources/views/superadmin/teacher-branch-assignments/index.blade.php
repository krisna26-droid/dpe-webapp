@extends('layouts.dashboard')

@section('title', 'Penugasan Guru')
@section('heading', 'Guru & Penugasan')
@section('description', 'Pantau cabang yang terhubung dengan setiap guru aktif.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Penugasan</p>
            <h2>Penugasan guru ke cabang</h2>
            <p>Daftar penempatan guru pada cabang beserta periode tugasnya.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.teacher-branch-assignments.create') }}">
                <x-dashboard.icon name="teacher" />
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
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="teacher" /> Guru</span>
        </header>

        @if ($assignments->count() === 0)
            <x-dashboard.empty
                title="Belum ada penugasan guru"
                message="Penugasan guru akan tampil setelah data assignment tersedia."
                icon="teacher"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Guru</th>
                            <th>Cabang</th>
                            <th>Mulai</th>
                            <th>Berakhir</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignments as $assignment)
                            @php($active = $assignment->isActiveOn())
                            <tr>
                                <td>
                                    <strong>{{ $assignment->teacher?->user?->full_name ?: ($assignment->teacher?->user?->username ?: '—') }}</strong>
                                </td>
                                <td>
                                    <strong>{{ $assignment->branch?->name ?: '—' }}</strong>
                                    @if ($assignment->branch?->code)<br><span>{{ $assignment->branch->code }}</span>@endif
                                </td>
                                <td>{{ $assignment->starts_on?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $assignment->ends_on?->format('d M Y') ?? 'Berjalan' }}</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
                                        {{ $active ? 'Aktif' : ($assignment->starts_on->isFuture() ? 'Belum dimulai' : 'Selesai') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="dpe-operations-actions">
                                        <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.teacher-branch-assignments.show', $assignment) }}">Detail</a>
                                        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.teacher-branch-assignments.edit', $assignment) }}">Edit</a>
                                    </div>
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
