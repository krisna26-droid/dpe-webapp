@props([
    'rows' => 3,
    'showHeader' => true,
])

<div {{ $attributes->class(['dpe-dashboard-state', 'dpe-dashboard-state--loading']) }} role="status" aria-live="polite" aria-label="Memuat data">
    @if ($showHeader)
        <div class="dpe-dashboard-skeleton dpe-dashboard-skeleton--heading"></div>
    @endif
    <div class="dpe-dashboard-skeleton-list">
        @for ($row = 0; $row < $rows; $row++)
            <div class="dpe-dashboard-skeleton-row">
                <span class="dpe-dashboard-skeleton dpe-dashboard-skeleton--avatar"></span>
                <span class="dpe-dashboard-skeleton dpe-dashboard-skeleton--line dpe-dashboard-skeleton--line-wide"></span>
                <span class="dpe-dashboard-skeleton dpe-dashboard-skeleton--line dpe-dashboard-skeleton--line-short"></span>
            </div>
        @endfor
    </div>
    <span class="sr-only">Memuat data...</span>
</div>
