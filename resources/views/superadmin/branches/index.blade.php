@extends('layouts.dashboard')

@section('title', 'Manajemen Cabang')
@section('heading', 'Cabang')
@section('description', 'Lihat status dan konfigurasi seluruh cabang DPE.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Cabang</p>
            <h2>Manajemen cabang</h2>
            <p>Daftar cabang yang terdaftar di sistem pusat beserta status operasionalnya.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.branches.create') }}">
                <x-dashboard.icon name="building" />
                <span>Tambah cabang</span>
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
                <h2>Daftar cabang</h2>
                <p>{{ $branches->total() }} cabang tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="building" /> Data pusat</span>
        </header>

        @if ($branches->count() === 0)
            <x-dashboard.empty
                title="Belum ada cabang"
                message="Belum ada data cabang yang tersimpan. Daftar akan muncul di sini setelah cabang dibuat."
                icon="building"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama cabang</th>
                            <th>Zona waktu</th>
                            <th>Hari rekap</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($branches as $branch)
                            <tr>
                                <td><a href="{{ route('superadmin.branches.show', $branch) }}"><strong>{{ $branch->code }}</strong></a></td>
                                <td><a href="{{ route('superadmin.branches.show', $branch) }}"><strong>{{ $branch->name }}</strong></a></td>
                                <td>{{ $branch->timezone_name }}</td>
                                <td>Tanggal {{ $branch->payment_recap_day }}</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $branch->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">
                                        {{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td>{{ $branch->created_at?->format('d M Y') ?? '—' }}</td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.branches.edit', $branch) }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
                    <x-dashboard.pagination :paginator="$branches" />
        @endif
    </article>
@endsection
