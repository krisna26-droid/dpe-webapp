@extends('layouts.dashboard')

@section('title', 'Tagihan Bulanan')
@section('heading', 'Tagihan Bulanan')
@section('description', 'Kelola tagihan bulanan siswa dan pantau status bukti pembayarannya.')

@section('content')
    @php
        $routePrefix = auth()->user()->role_code === 'admin' ? 'admin' : 'superadmin';
    @endphp
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Laporan & Keuangan / Tagihan</p>
            <h2>Tagihan bulanan</h2>
            <p>Catat nominal tagihan per siswa, tetapkan jatuh tempo, dan lihat status pembayaran yang masuk.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route($routePrefix.'.monthly-charges.create') }}">
                <x-dashboard.icon name="wallet" />
                <span>Tambah tagihan</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routePrefix.'.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar tagihan</h2>
                <p>{{ $students->total() }} siswa · {{ $month->locale('id')->translatedFormat('F Y') }}</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="wallet" /> Keuangan siswa</span>
        </header>

        <div class="dpe-dashboard-card__body">
            <form method="GET" action="{{ route($routePrefix.'.monthly-charges.index') }}" class="dpe-operations-filter">
                <div class="dpe-dashboard-field">
                    <label for="monthly-charge-month">Bulan tagihan</label>
                    <input id="monthly-charge-month" name="month" type="month" value="{{ $filters['month'] }}" required>
                </div>
                @if ($canFilterBranch)
                    <div class="dpe-dashboard-field">
                        <label for="monthly-charge-branch">Cabang</label>
                        <select id="monthly-charge-branch" name="branch_id">
                            <option value="">Semua cabang</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($filters['branch_id'] === $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="dpe-dashboard-field">
                    <label for="monthly-charge-teacher">Guru</label>
                    <select id="monthly-charge-teacher" name="teacher_filter_id">
                        <option value="">Semua guru</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected($filters['teacher_filter_id'] === $teacher->id)>{{ $teacher->user->full_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="dpe-dashboard-field">
                    <label for="monthly-charge-student">Nama siswa</label>
                    <input id="monthly-charge-student" name="q" type="search" maxlength="120" value="{{ $filters['q'] }}" placeholder="Cari nama siswa">
                </div>
                <div class="dpe-dashboard-field">
                    <label for="monthly-charge-status">Status tagihan</label>
                    <select id="monthly-charge-status" name="charge_status">
                        <option value="all" @selected($filters['charge_status'] === 'all')>Semua status</option>
                        <option value="created" @selected($filters['charge_status'] === 'created')>Sudah dibuat</option>
                        <option value="missing" @selected($filters['charge_status'] === 'missing')>Belum dibuat</option>
                    </select>
                </div>
                <div class="dpe-operations-actions">
                    <button class="dpe-dashboard-button dpe-dashboard-button--primary" type="submit">Terapkan filter</button>
                    <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($routePrefix.'.monthly-charges.index') }}">Reset filter</a>
                </div>
            </form>
            @if ($teacherUnavailable)
                <p class="dpe-operations-warning" role="status">Guru yang dipilih tidak tersedia untuk bulan atau cabang ini. Pilih guru kembali.</p>
            @endif
        </div>

        @if ($students->isEmpty())
            <x-dashboard.empty
                title="Tidak ada siswa yang sesuai"
                message="Coba ubah bulan atau pilihan filter."
                icon="wallet"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th>Siswa</th>
                            <th>Cabang</th>
                            <th>Nominal</th>
                            <th>Jatuh tempo</th>
                            <th>Status tagihan</th>
                            <th>Status pembayaran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @forelse ($student->monthlyCharges as $monthlyCharge)
                                @php
                                    $proofs = $monthlyCharge->paymentProofs;
                                    $approvedProof = $proofs->firstWhere('status', 'approved');
                                    $latestProof = $proofs->first();
                                    $latestStatus = strtolower((string) $latestProof?->status);
                                    $statusLabel = 'Belum dibayar';
                                    $statusClass = 'dpe-dashboard-badge--warning';

                                    if ($approvedProof) {
                                        $statusLabel = 'Lunas';
                                        $statusClass = 'dpe-dashboard-badge--success';
                                    } elseif ($latestStatus === 'pending') {
                                        $statusLabel = 'Menunggu review';
                                    } elseif ($latestStatus === 'rejected') {
                                        $statusLabel = 'Bukti ditolak';
                                        $statusClass = 'dpe-dashboard-badge--danger';
                                    } elseif ($monthlyCharge->due_on?->isBefore(now()->startOfDay())) {
                                        $statusLabel = 'Terlambat';
                                        $statusClass = 'dpe-dashboard-badge--danger';
                                    }
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route($routePrefix.'.monthly-charges.show', $monthlyCharge) }}">
                                            <strong>{{ $monthlyCharge->charge_month?->format('M Y') ?: '—' }}</strong>
                                        </a>
                                    </td>
                                    <td>{{ $student->full_name }}</td>
                                    <td>{{ $monthlyCharge->branch?->name ?: 'Cabang tidak ditemukan' }}</td>
                                    <td><strong>{{ $monthlyCharge->amount_idr === null ? 'Belum ditetapkan' : 'Rp '.number_format((float) $monthlyCharge->amount_idr, 2, ',', '.') }}</strong></td>
                                    <td>{{ $monthlyCharge->due_on?->format('d M Y') ?: 'Tidak ditentukan' }}</td>
                                    <td><span class="dpe-dashboard-badge dpe-dashboard-badge--success">Sudah dibuat</span></td>
                                    <td><span class="dpe-dashboard-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                    <td>
                                        <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($routePrefix.'.monthly-charges.show', $monthlyCharge) }}">Detail</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td><strong>{{ $month->locale('id')->translatedFormat('M Y') }}</strong></td>
                                    <td>{{ $student->full_name }}</td>
                                    <td>{{ $student->enrollments->first()?->branch?->name ?: '—' }}</td>
                                    <td>—</td>
                                    <td>—</td>
                                    <td><span class="dpe-dashboard-badge dpe-dashboard-badge--warning">Belum dibuat</span></td>
                                    <td>—</td>
                                    <td>—</td>
                                </tr>
                            @endforelse
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <x-dashboard.pagination :paginator="$students" />
    </article>
@endsection
