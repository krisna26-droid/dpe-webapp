@php
    $memberships=$student?->classGroupMemberships ?? collect();
    if(auth()->user()->role_code==='admin') {
        $branchIds=app(\App\Services\StudentAccountProvisioningService::class)->branches(auth()->user())->pluck('id');
        $memberships=$memberships->filter(fn($m)=>$branchIds->contains($m->classGroup?->branch_id));
    }
@endphp
@if($student)
<section class="dpe-dashboard-card"><header class="dpe-dashboard-card__head"><h2>Guru dan sesi belajar</h2></header><div class="dpe-dashboard-card__body">
@foreach($student->guardians as $guardian)@if($guardian->is_primary)<p>Telepon wali: {{ $guardian->whatsapp_number }}</p>@endif @endforeach
@foreach($memberships as $member)<p><strong>{{ $member->classGroup?->name }}</strong> · {{ $member->starts_on?->format('d/m/Y') }}–{{ $member->ends_on?->format('d/m/Y') ?? 'Berjalan' }}</p>
@if(!$member->session_selection_from)<p>Mengikuti seluruh sesi kelas sampai pilihan sesi ditetapkan.</p>@endif
@foreach($member->learningSessionEnrollments as $link)@php
    $end=$link->ends_on;
    if($member->ends_on && (!$end || $member->ends_on->lt($end))) $end=$member->ends_on;
@endphp
@if(!$end || $link->starts_on->lte($end))<p>{{ $link->weekday ? (\App\Services\LessonSeriesService::DAYS[$link->weekday].' · ') : '' }}{{ $link->learningSession?->name }} · {{ $link->starts_on->format('d/m/Y') }}–{{ $end?->format('d/m/Y') ?? 'Berjalan' }}</p>@endif
@endforeach @endforeach
</div></section>
@endif
