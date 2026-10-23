@extends('layouts.dashboard')

@section('title', 'Ketersediaan Guru')
@section('heading', 'Ketersediaan Guru')
@section('description', 'Atur slot waktu guru yang dapat digunakan untuk penjadwalan sesi belajar.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Jadwal</p>
            <h2>Ketersediaan guru</h2>
            <p>Kelola hari, waktu, jenis kelas, dan status slot mengajar di setiap cabang.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.teacher-availability.create') }}">
                <x-dashboard.icon name="clock" />
                <span>Tambah slot</span>
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
                <h2>Daftar slot ketersediaan</h2>
                <p>{{ $availabilities->total() }} slot tercatat</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="clock" /> Ketersediaan</span>
        </header>

        @if ($availabilities->count() === 0)
            <x-dashboard.empty
                title="Belum ada slot ketersediaan"
                message="Tambahkan slot agar jadwal sesi belajar dapat disusun berdasarkan waktu guru."
                icon="clock"
                :action-url="route('superadmin.teacher-availability.create')"
                action-label="Tambah slot"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Guru</th>
                            <th>Cabang</th>
                            <th>Waktu</th>
                            <th>Jenis / status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($availabilities as $availability)
                            @php
                                $teacherName = $availability->teacher?->user?->full_name ?: ($availability->teacher?->user?->username ?: '—');
                                $status = strtolower((string) $availability->availability_status);
                                $statusClass = in_array($status, ['available', 'tersedia', 'open', 'aktif'], true)
                                    ? 'dpe-dashboard-badge--success'
                                    : (in_array($status, ['blocked', 'blocked_out', 'unavailable', 'nonaktif'], true) ? 'dpe-dashboard-badge--danger' : 'dpe-dashboard-badge--warning');
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('superadmin.teacher-availability.show', $availability) }}">
                                        <strong>{{ $availability->available_on?->format('d M Y') ?? '—' }}</strong>
                                    </a>
                                </td>
                                <td>{{ $teacherName }}</td>
                                <td>{{ $availability->branch?->name ?: '—' }}</td>
                                <td>{{ substr((string) $availability->starts_at, 0, 5) }}–{{ substr((string) $availability->ends_at, 0, 5) }}</td>
                                <td>
                                    <span class="dpe-dashboard-badge {{ $statusClass }}">
                                        {{ $availability->class_type ?: '—' }} · {{ $availability->availability_status ?: '—' }}
                                    </span>
                                </td>
                                <td>
                                    <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.teacher-availability.edit', $availability) }}">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$availabilities" />
        @endif
    </article>
@endsection
