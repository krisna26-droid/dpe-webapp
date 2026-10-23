@extends('layouts.dashboard')
@section('title', 'Edit Slot Ketersediaan')
@section('heading', 'Edit Slot Ketersediaan')
@section('description', 'Perbarui waktu atau status slot ketersediaan milik Anda.')
@section('content')
    <section class="dpe-dashboard-page-header"><div class="dpe-dashboard-page-header__copy"><p class="dpe-dashboard-kicker">Teacher / Slot Ketersediaan</p><h2>Edit slot mengajar.</h2><p>{{ $slot->available_on->format('d M Y') }} · {{ $slot->branch?->name }}</p></div><a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ route('teacher.availability.show', $slot) }}"><x-dashboard.icon name="back" />Kembali ke detail slot</a></section>
    <article class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><div><h2>Data slot</h2><p>Periksa kembali tanggal dan waktu sebelum menyimpan.</p></div></header><div class="dpe-dashboard-card__body"><form method="POST" action="{{ route('teacher.availability.update', $slot) }}" class="dpe-dashboard-form dpe-teacher-stack">@csrf @method('PUT') @include('teacher.availability._form')</form></div></article>
@endsection
