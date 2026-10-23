@props([
    'title' => 'Data belum dapat dimuat',
    'message' => 'Coba lagi beberapa saat lagi.',
    'retry' => true,
    'retryLabel' => 'Coba lagi',
])

<div {{ $attributes->class(['dpe-dashboard-state', 'dpe-dashboard-state--error']) }} role="alert">
    <div class="dpe-dashboard-state__icon">
        <x-dashboard.icon name="warning" />
    </div>
    <div class="dpe-dashboard-state__body">
        <h3>{{ $title }}</h3>
        <p>{{ $message }}</p>
        @if ($retry)
            <button class="dpe-dashboard-button dpe-dashboard-button--soft" type="button" data-dashboard-retry>
                <x-dashboard.icon name="refresh" />
                <span>{{ $retryLabel }}</span>
            </button>
        @endif
    </div>
</div>
