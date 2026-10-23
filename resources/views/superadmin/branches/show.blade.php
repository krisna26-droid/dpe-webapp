@extends('layouts.dashboard')

@section('title', $branch->name)
@section('heading', 'Detail Cabang')
@section('description', 'Informasi cabang dan status operasionalnya.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Cabang</p>
            <h2>{{ $branch->name }}</h2>
            <p>{{ $branch->code }} · {{ $branch->timezone_name }}</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.branches.edit', $branch) }}">
                <x-dashboard.icon name="settings" />
                <span>Edit cabang</span>
            </a>
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.branches.index') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali</span>
            </a>
        </div>
    </section>

    <section class="dpe-dashboard-grid dpe-dashboard-grid--two">
        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Informasi cabang</h2>
                    <p>Data dasar yang digunakan oleh modul operasional.</p>
                </div>
                <span class="dpe-dashboard-badge {{ $branch->is_active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--danger' }}">
                    {{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </header>
            <div class="dpe-dashboard-card__body">
                <div class="dpe-dashboard-detail-grid">
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Kode cabang</p>
                        <p class="dpe-dashboard-detail__value">{{ $branch->code }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Zona waktu</p>
                        <p class="dpe-dashboard-detail__value">{{ $branch->timezone_name }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Tanggal rekap pembayaran</p>
                        <p class="dpe-dashboard-detail__value">Tanggal {{ $branch->payment_recap_day }}</p>
                    </div>
                    <div class="dpe-dashboard-detail">
                        <p class="dpe-dashboard-detail__label">Dibuat</p>
                        <p class="dpe-dashboard-detail__value">{{ $branch->created_at?->format('d M Y H:i') ?? '—' }}</p>
                    </div>
                    <div class="dpe-dashboard-detail dpe-dashboard-detail--full">
                        <p class="dpe-dashboard-detail__label">Alamat</p>
                        <p class="dpe-dashboard-detail__value dpe-dashboard-detail__value--normal">{{ $branch->address ?: 'Belum diisi' }}</p>
                    </div>
                </div>
            </div>
        </article>

        <article class="dpe-dashboard-card">
            <header class="dpe-dashboard-card__head">
                <div>
                    <h2>Status operasional</h2>
                    <p>Atur apakah cabang dapat digunakan untuk data baru.</p>
                </div>
            </header>
            <div class="dpe-dashboard-card__body">
                @if ($branch->is_active)
                    <x-dashboard.empty
                        title="Cabang sedang aktif"
                        message="Cabang ini dapat dipilih untuk penugasan, jadwal, dan data operasional baru."
                        icon="check"
                    />
                @else
                    <x-dashboard.empty
                        title="Cabang sedang nonaktif"
                        message="Cabang ini tidak seharusnya menerima data operasional baru sampai diaktifkan kembali."
                        icon="warning"
                    />
                @endif

                <form method="POST" action="{{ route('superadmin.branches.status', $branch) }}" class="dpe-dashboard-form-actions">
                    @csrf
                    @method('PATCH')
                    <button class="dpe-dashboard-button {{ $branch->is_active ? 'dpe-dashboard-button--soft' : 'dpe-dashboard-button--primary' }}" type="submit">
                        <x-dashboard.icon name="refresh" />
                        <span>{{ $branch->is_active ? 'Nonaktifkan cabang' : 'Aktifkan cabang' }}</span>
                    </button>
                </form>
            </div>
        </article>
    </section>
@endsection
