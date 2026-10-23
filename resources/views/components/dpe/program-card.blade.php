@props(['program', 'index' => 0])
<article class="program-card">
    <div class="program-card-top"><span class="icon-tile"><x-dpe.icon :name="['users', 'message', 'target'][$index % 3]" /></span><span class="tag">{{ $program['type_label'] }}</span></div>
    @if ($program['image'])<img class="program-card-image" src="{{ $program['image'] }}" alt="{{ $program['name'] }}" loading="lazy" width="400" height="240">@endif
    <h3>{{ $program['name'] }}</h3><p>{{ $program['description'] ?: 'Informasi program selengkapnya dapat dikonsultasikan melalui kontak DPE.' }}</p>
    @if ($program['features'])<ul class="check-list">@foreach (array_slice($program['features'], 0, 2) as $feature)<li><x-dpe.icon name="check" /><span>{{ $feature }}</span></li>@endforeach</ul>@endif
    <a class="text-link card-link" href="{{ route('public.program', $program['id']) }}" aria-label="Lihat program {{ $program['name'] }}">Lihat program <x-dpe.icon name="arrow" /></a>
</article>
