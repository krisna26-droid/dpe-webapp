@php
    $sessionChoices = app(\App\Services\ClassLearningSessionService::class)->choices($group->learningSessions);
@endphp
<section class="dpe-dashboard-card">
    <header class="dpe-dashboard-card__head"><div><h2>Sesi belajar kelompok</h2><p>Siswa dapat mengikuti satu atau beberapa sesi pada hari yang dipilih.</p></div></header>
    <div class="dpe-dashboard-card__body">
        @forelse($sessionChoices as $choice)
            <p>{{ $choice['day_name'] }} · {{ $choice['session']->name }} · {{ $choice['details'] }}</p>
        @empty
            <p>Belum ada sesi. Tambahkan sesi saat membuat jadwal.</p>
        @endforelse
    </div>
</section>
