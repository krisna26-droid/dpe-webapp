@props(['membership', 'date' => null])

@php
    $date ??= \Illuminate\Support\Carbon::today();
    $student = $membership->student;
    $active = $membership->isActiveParticipant($date);
    $label = match (true) {
        $student?->trashed() === true => 'Siswa dihapus',
        $student === null => 'Siswa tidak ditemukan',
        $active => 'Aktif',
        $membership->starts_on->gt($date) => 'Belum mulai',
        default => 'Selesai',
    };
@endphp

<span class="dpe-dashboard-badge {{ $active ? 'dpe-dashboard-badge--success' : 'dpe-dashboard-badge--warning' }}">
    {{ $label }}
</span>
