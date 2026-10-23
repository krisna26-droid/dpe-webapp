@extends('layouts.dashboard')

@section('title', 'Wali Siswa')
@section('heading', 'Wali Siswa')
@section('description', 'Kelola kontak orang tua atau wali sebagai penerima komunikasi dan rapor.')

@section('content')
    <section class="dpe-dashboard-page-header">
        <div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Siswa / Wali</p><h2>Wali dan penerima rapor</h2><p>Satu siswa dapat memiliki beberapa kontak, dengan satu penerima utama pada satu waktu.</p></div>
        <div class="dpe-dashboard-page-header__actions"><a class="dpe-dashboard-button dpe-dashboard-button--primary" href="{{ route('superadmin.guardians.create') }}"><x-dashboard.icon name="users" /><span>Tambah wali</span></a><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('superadmin.students.index') }}"><x-dashboard.icon name="student" /><span>Profil siswa</span></a></div>
    </section>

    <article class="dpe-dashboard-card">
        <header class="dpe-dashboard-card__head"><div><h2>Daftar wali siswa</h2><p>{{ $guardians->total() }} kontak tercatat</p></div><span class="dpe-dashboard-badge"><x-dashboard.icon name="users" /> Data pusat</span></header>
        @if ($guardians->count() === 0)
            <x-dashboard.empty title="Belum ada wali siswa" message="Tambahkan nomor WhatsApp wali dari sini atau dari detail profil siswa." action-url="{{ route('superadmin.guardians.create') }}" action-label="Tambah wali" icon="users" />
        @else
            <div class="dpe-dashboard-table-wrap">
                <table class="dpe-dashboard-table">
                    <thead><tr><th>Wali / penerima</th><th>Siswa</th><th>Hubungan</th><th>WhatsApp</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($guardians as $guardian)
                            <tr>
                                <td><a href="{{ route('superadmin.guardians.show', $guardian) }}"><strong>{{ $guardian->full_name }}</strong></a></td>
                                <td><a href="{{ route('superadmin.students.show', $guardian->student) }}">{{ $guardian->student?->full_name ?: '—' }}</a></td>
                                <td>{{ $guardian->relationship_name ?: '—' }}</td>
                                <td>{{ $guardian->whatsapp_number }}</td>
                                <td><span class="dpe-dashboard-badge {{ $guardian->is_primary ? 'dpe-dashboard-badge--success' : '' }}">{{ $guardian->is_primary ? 'Penerima utama' : 'Kontak alternatif' }}</span></td>
                                <td><a class="dpe-dashboard-button dpe-dashboard-button--soft" href="{{ route('superadmin.guardians.edit', $guardian) }}">Edit</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$guardians" />
        @endif
    </article>
@endsection
