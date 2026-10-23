@if (empty($videos))
    <p class="dpe-portal-muted">Belum ada tautan video pada laporan yang disetujui ini.</p>
@else
    <div class="dpe-portal-stack">
        @foreach ($videos as $video)
            <div class="dpe-portal-item">
                <strong>{{ $video['topic'] ?: 'Video pembelajaran' }}</strong>
                <small>{{ $video['video_on'] ?: 'Tanggal belum tersedia' }} · {{ $video['teacher_name'] ?: 'Teacher' }}</small>
                <p class="dpe-portal-text">{{ $video['description'] ?: '—' }}</p>
                @if ($video['video_url'])
                    <a class="dpe-dashboard-button dpe-dashboard-button--outline" href="{{ $video['video_url'] }}" target="_blank" rel="noopener noreferrer">Buka Video</a>
                @else
                    <span class="dpe-portal-muted">Tautan video belum tersedia.</span>
                @endif
            </div>
        @endforeach
    </div>
@endif

