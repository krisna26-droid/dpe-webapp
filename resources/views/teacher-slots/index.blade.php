@php($operationRoutePrefix = $operationRoutePrefix ?? 'admin')
@extends('layouts.dashboard')

@section('title', 'Pengaturan Slot Guru')
@section('heading', 'Pengaturan Slot Guru')
@section('description', 'Atur kapasitas siswa pada setiap sesi belajar yang sudah memiliki jadwal.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy">
            <p class="dpe-dashboard-kicker">Operasional / Jadwal</p>
            <h2>Kapasitas siswa per sesi</h2>
            <p>Hari dan jam mengikuti jadwal sesi. Ubah jumlah slot sesuai kapasitas kelas pada cabang Anda.</p>
        </div>
        <div class="dpe-dashboard-page-header__actions">
            <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route($operationRoutePrefix === 'superadmin' ? 'superadmin.dashboard' : 'admin.dashboard') }}">
                <x-dashboard.icon name="back" />
                <span>Kembali ke dashboard</span>
            </a>
        </div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head">
            <div>
                <h2>Daftar slot sesi</h2>
                <p>{{ $rows->count() }} sesi dengan jadwal</p>
            </div>
            <span class="dpe-dashboard-badge"><x-dashboard.icon name="users" /> Kapasitas siswa</span>
        </header>

        @if ($rows->isEmpty())
            <x-dashboard.empty
                title="Belum ada sesi terjadwal"
                message="Buat jadwal sesi belajar terlebih dahulu. Sesi yang sudah memiliki jadwal akan muncul di sini."
                icon="calendar"
                :action-url="route($operationRoutePrefix.'.lesson-series.create')"
                action-label="Buat jadwal mingguan"
            />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead>
                        <tr>
                            <th>Guru</th>
                            <th>Kelompok / cabang</th>
                            <th>Hari dan jam</th>
                            <th>Sesi</th>
                            <th>Kapasitas</th>
                            <th>Terisi</th>
                            <th>Sisa</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr id="slot-row-{{ $row['id'] }}">
                                <td><strong>{{ $row['teacher_name'] ?: 'Guru belum tersedia' }}</strong></td>
                                <td>
                                    <strong>{{ $row['class_group_name'] }}</strong>
                                    <small>{{ $row['branch_name'] }}{{ $row['class_group_code'] ? ' · '.$row['class_group_code'] : '' }}</small>
                                </td>
                                <td>
                                    @foreach ($row['details'] as $detail)
                                        <div><strong>{{ $detail['day_name'] }}</strong> · {{ $detail['starts_at'] ?: '—' }}–{{ $detail['ends_at'] ?: '—' }}</div>
                                    @endforeach
                                </td>
                                <td><span class="dpe-dashboard-badge">{{ $row['session_name'] }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route($operationRoutePrefix.'.teacher-slots.update', $row['id']) }}" class="dpe-dashboard-table-actions" data-teacher-slot-form>
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="class_learning_session_id" value="{{ $row['id'] }}">
                                        <label class="sr-only" for="slot_capacity_{{ $row['id'] }}">Jumlah slot {{ $row['session_name'] }}</label>
                                        <input class="dpe-slot-capacity-input" id="slot_capacity_{{ $row['id'] }}" name="slot_capacity" type="number" min="1" max="500" value="{{ old('class_learning_session_id') === $row['id'] ? old('slot_capacity') : $row['slot_capacity'] }}" placeholder="—" required>
                                        <button class="dpe-dashboard-button dpe-dashboard-button--soft" type="submit">Simpan</button>
                                    </form>
                                    @if ($errors->has('slot_capacity') && old('class_learning_session_id') === $row['id'])
                                        <p class="dpe-dashboard-field__error">{{ $errors->first('slot_capacity') }}</p>
                                    @endif
                                </td>
                                <td>{{ $row['filled'] }}</td>
                                <td>
                                    @if ($row['remaining'] === null)
                                        <span class="dpe-dashboard-badge dpe-dashboard-badge--warning">Belum diatur</span>
                                    @else
                                        <span class="dpe-dashboard-badge {{ $row['remaining'] < 0 ? 'dpe-dashboard-badge--danger' : ($row['remaining'] === 0 ? 'dpe-dashboard-badge--warning' : 'dpe-dashboard-badge--success') }}">{{ $row['remaining'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route($operationRoutePrefix.'.class-groups.show', $row['class_group_id']) }}" aria-label="Buka detail kelas">Detail kelas</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>

    <p class="dpe-dashboard-field__hint" style="margin-top: 16px;">Terisi menghitung siswa aktif yang terhubung ke sesi ini hari ini. Sesi dengan kapasitas kosong belum dibatasi sampai jumlah slot diisi.</p>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const storageKey = 'dpe-teacher-slots-scroll-' + window.location.pathname;
            const savedValue = window.sessionStorage.getItem(storageKey);
            const savedPosition = savedValue === null ? null : Number(savedValue);

            if (savedPosition !== null && Number.isFinite(savedPosition)) {
                window.requestAnimationFrame(function () {
                    window.requestAnimationFrame(function () {
                        window.scrollTo(0, savedPosition);
                        window.sessionStorage.removeItem(storageKey);
                    });
                });
            }

            document.querySelectorAll('[data-teacher-slot-form]').forEach(function (form) {
                form.addEventListener('submit', function () {
                    window.sessionStorage.setItem(storageKey, String(window.scrollY));
                });
            });
        });
    </script>
@endpush
